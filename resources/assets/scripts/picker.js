/*!
 * laranail/emojis picker — a dependency-free emoji picker over the package's picker payload.
 *
 *   import { Picker, ApiSource } from '/vendor/laranail/emojis/js/picker.js';
 *
 *   Picker.create(document.querySelector('#picker'))
 *     .source(new ApiSource('/laranail/emojis/api/v1'))
 *     .target(document.querySelector('#message'))
 *     .on('select', ({ emoji }) => console.log(emoji))
 *     .mount();
 *
 * Or no JavaScript at all: <div data-laranail-emoji-picker data-laranail-emoji-target="#message"
 * data-laranail-emoji-source="/laranail/emojis/api/v1/picker"></div>, which autoInit() mounts, including
 * elements added later (Livewire, wire:navigate, Turbo).
 *
 * CSP-safe: the DOM is built with createElement and textContent only, never innerHTML or eval, and the
 * only data block it reads is <script type="application/json">, which no policy executes.
 */

const PREFIX = 'laranail-emoji';
const ATTR = `data-${PREFIX}`;
const EVENT = `${PREFIX}:select`;
const MOUNTED = Symbol.for('laranail-emoji-picker');

const STRINGS = {
  search: 'Search emoji',
  results: '{count} results',
  noResults: 'No emoji found',
  recent: 'Frequently used',
  custom: 'Custom',
  tone: 'Skin tone',
  tones: ['Default', 'Light', 'Medium-light', 'Medium', 'Medium-dark', 'Dark'],
  open: 'Choose an emoji',
  loading: 'Loading emoji',
  failed: 'Emoji could not be loaded',
};

// ---- pure helpers (exported for tests) ---------------------------------------------------------------

/** Lower-case and strip diacritics, so "fusee" finds "fusée" and "CAFE" finds "café". */
export function fold(text) {
  return String(text).normalize('NFD').replace(/\p{M}+/gu, '').toLowerCase().trim();
}

/** "1F44B-1F3FD" → "👋🏽". */
export function charOf(hexcode) {
  return hexcode.split('-').map((part) => String.fromCodePoint(parseInt(part, 16))).join('');
}

/** The hexcode an emoji takes with a skin tone (1–5), or its own when it takes none. */
export function withTone(item, tone) {
  if (!tone || !item.skins) {
    return item.hexcode;
  }

  // One person: "3". Several (🤝, 💏): the same tone on each, "3-3".
  return item.skins[String(tone)] ?? item.skins[`${tone}-${tone}`] ?? item.hexcode;
}

/** Compares two dotted Emoji versions ("15.1" > "15.0"). */
export const byVersion = (a, b) => {
  const [am, an = 0] = String(a).split('.').map(Number);
  const [bm, bn = 0] = String(b).split('.').map(Number);

  return am - bm || an - bn;
};

/**
 * One emoji per Emoji version, newest first, to find what this platform draws. ZWJ sequences stand for the
 * versions that added only sequences, since a platform without them draws two glyphs side by side.
 */
const VERSION_SAMPLES = [
  ['18.0', '\u{1FAEB}'], ['17.0', '\u{1FAEA}'], ['16.0', '\u{1FAE9}'], ['15.1', '\u{1F642}\u200D\u2194\uFE0F'],
  ['15.0', '\u{1FAE8}'], ['14.0', '\u{1FAE0}'], ['13.1', '\u{1F62E}\u200D\u{1F4A8}'], ['13.0', '\u{1F972}'],
  ['12.1', '\u{1F9D1}\u200D\u{1F9B0}'], ['12.0', '\u{1F971}'], ['11.0', '\u{1F970}'],
];

/**
 * The newest Emoji version this browser draws, or null when it cannot tell (no canvas, as in tests and
 * old browsers): then nothing is hidden. An emoji the font lacks draws exactly like an unassigned code point
 * (the "tofu" box), and a sequence it lacks draws as its parts, wider than one emoji.
 */
export function detectMaxVersion(doc = globalThis.document) {
  const canvas = doc?.createElement?.('canvas');
  const context = canvas?.getContext?.('2d', { willReadFrequently: true });

  if (!context || typeof context.getImageData !== 'function') {
    return null;
  }

  const size = 24;
  canvas.width = canvas.height = size * 2;
  context.font = `${size}px 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif`;
  context.textBaseline = 'top';

  const draw = (text) => {
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.fillText(text, 0, 0);

    return context.getImageData(0, 0, canvas.width, canvas.height).data.join(',');
  };

  const tofu = draw('\u{10FFFD}');
  const single = context.measureText('\u{1F600}').width;

  for (const [version, sample] of VERSION_SAMPLES) {
    if (draw(sample) !== tofu && context.measureText(sample).width < single * 1.5) {
      return version;
    }
  }

  return '11.0';
}

/**
 * Ranks emoji for a search term: exact name, name prefix, shortcode, keyword prefix, anywhere. Every word
 * of the term must match somewhere.
 */
export function search(items, term) {
  const words = fold(term).split(/\s+/).filter(Boolean);

  if (words.length === 0) {
    return [];
  }

  const phrase = words.join(' ');
  const scored = [];

  for (const item of items) {
    const name = fold(item.name);
    const code = fold(item.shortcode ?? '');
    const keywords = (item.keywords ?? []).map(fold);
    const haystack = `${name} ${code} ${keywords.join(' ')}`;

    if (!words.every((word) => haystack.includes(word))) {
      continue;
    }

    const score =
      name === phrase ? 0
      : name.startsWith(phrase) ? 1
      : code === phrase.replace(/\s+/g, '_') ? 2
      : keywords.some((k) => k.startsWith(words[0])) ? 3
      : 4;

    scored.push([score, scored.length, item]);
  }

  return scored.sort((a, b) => a[0] - b[0] || a[1] - b[1]).map(([, , item]) => item);
}

/** Orders a list: "default" keeps CLDR order, "name" sorts by name, "newest" puts the latest Emoji version first. */
export function sortItems(items, mode) {
  if (mode === 'name') {
    return [...items].sort((a, b) => a.name.localeCompare(b.name));
  }

  if (mode === 'newest') {
    return [...items].sort((a, b) => byVersion(b.version, a.version));
  }

  return items;
}

/**
 * Records a pick in the recent list: newest first, once per base emoji (a toned 👋🏽 replaces 👋), at most
 * `max` entries. Returns a new list; entries are { hexcode, base, count, at }.
 */
export function recordRecent(list, base, hexcode, max, now = Date.now()) {
  const previous = list.find((entry) => entry.base === base);
  const entry = { base, hexcode, count: (previous?.count ?? 0) + 1, at: now };

  return [entry, ...list.filter((e) => e.base !== base)].slice(0, Math.max(0, max));
}

/** Orders recents: "recent" by time of last use, "frequent" by count then time. */
export function orderRecent(list, mode) {
  return [...list].sort((a, b) => (mode === 'frequent' ? b.count - a.count || b.at - a.at : b.at - a.at));
}

/** Reads every data-laranail-emoji-* option on an element, typed. Unknown attributes are ignored. */
export function parseOptions(element) {
  const data = (name) => element.getAttribute(`${ATTR}-${name}`);
  const int = (name, fallback) => {
    const value = Number.parseInt(data(name) ?? '', 10);

    return Number.isFinite(value) ? value : fallback;
  };
  const list = (name) => (data(name) ?? '').split(',').map((v) => v.trim()).filter(Boolean);
  const strings = data('strings');

  return {
    target: data('target'),
    source: data('source'),
    payload: data('payload'),
    locale: data('locale'),
    tone: int('tone', 0),
    maxRecent: int('max-recent', 36),
    recentOrder: data('recent-order') === 'frequent' ? 'frequent' : 'recent',
    sort: ['name', 'newest'].includes(data('sort')) ? data('sort') : 'default',
    categories: list('categories'),
    columns: int('columns', 8),
    maxVersion: data('max-version') ?? 'auto',
    closeOnSelect: data('close-on-select') !== 'false',
    inline: element.hasAttribute(`${ATTR}-inline`),
    userKey: data('user-key') ?? '',
    strings: strings ? safeJson(strings, {}) : {},
  };
}

function safeJson(text, fallback) {
  try {
    return JSON.parse(text);
  } catch {
    return fallback;
  }
}

// ---- sources and stores --------------------------------------------------------------------------------

/** Loads the payload from the package's API (`…/api/v1`) or straight from a `…/picker` URL. */
export class ApiSource {
  constructor(url, init = {}) {
    this.url = String(url).replace(/\/+$/, '');
    this.init = init;
  }

  async load(locale) {
    const base = this.url.endsWith('/picker') ? this.url : `${this.url}/picker`;
    const url = locale ? `${base}?locale=${encodeURIComponent(locale)}` : base;
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', ...this.init });

    if (!response.ok) {
      throw new Error(`picker payload: HTTP ${response.status}`);
    }

    const body = await response.json();

    return body.data ?? body;
  }
}

/** A payload already in hand: an object, or the id of a <script type="application/json"> holding one. */
export class StaticSource {
  constructor(payload) {
    this.payload = payload;
  }

  async load() {
    if (typeof this.payload !== 'string') {
      return this.payload;
    }

    const block = document.getElementById(this.payload);

    if (!block) {
      throw new Error(`picker payload: no element #${this.payload}`);
    }

    return JSON.parse(block.textContent ?? '{}');
  }
}

/** A store that forgets on reload. */
export function memoryStore() {
  const map = new Map();

  return { get: (key) => map.get(key) ?? null, set: (key, value) => void map.set(key, value) };
}

/**
 * localStorage, namespaced, and never fatal: private mode, a full quota or blocked storage fall back to
 * memory. Only hexcodes, counts and times are written — never text the user typed.
 */
export function localStorageStore(namespace = PREFIX) {
  const fallback = memoryStore();
  const key = (name) => `${namespace}:${name}`;

  return {
    get(name) {
      try {
        const raw = globalThis.localStorage?.getItem(key(name));

        return raw === null || raw === undefined ? fallback.get(name) : JSON.parse(raw);
      } catch {
        return fallback.get(name);
      }
    },
    set(name, value) {
      fallback.set(name, value);

      try {
        globalThis.localStorage?.setItem(key(name), JSON.stringify(value));
      } catch {
        // Quota or privacy mode: memory keeps working for this page.
      }
    },
  };
}

// ---- the picker ------------------------------------------------------------------------------------------

const el = (tag, attributes = {}, text) => {
  const node = document.createElement(tag);

  for (const [name, value] of Object.entries(attributes)) {
    if (value !== null && value !== undefined && value !== false) {
      node.setAttribute(name, value === true ? '' : String(value));
    }
  }

  if (text !== undefined) {
    node.textContent = text;
  }

  return node;
};

export class Picker {
  /** Starts a picker on an element; chain the options, then mount(). */
  static create(element, options = {}) {
    return new Picker(element, options);
  }

  constructor(element, options = {}) {
    this.element = element;
    this.options = {
      locale: null,
      tone: 0,
      maxRecent: 36,
      recentOrder: 'recent',
      sort: 'default',
      categories: [],
      columns: 8,
      maxVersion: 'auto',
      closeOnSelect: true,
      inline: false,
      userKey: '',
      strings: {},
      ...options,
    };
    this.handlers = new Map();
    this.cleanup = [];
    this.data = null;
    this.query = '';
    this.active = null;
  }

  source(source) { this.options.source = source; return this; }
  locale(locale) { this.options.locale = locale; return this; }
  skinTone(tone) { this.options.tone = Math.max(0, Math.min(5, Number(tone) || 0)); return this; }
  sort(mode) { this.options.sort = mode; return this; }
  categories(list) { this.options.categories = [...list]; return this; }
  columns(count) { this.options.columns = Math.max(1, Number(count) || 8); return this; }
  /** Hide emoji newer than this Emoji version: 'auto' (default) asks the browser, null shows everything. */
  maxVersion(version) { this.options.maxVersion = version; return this; }
  closeOnSelect(close = true) { this.options.closeOnSelect = close; return this; }
  inline(inline = true) { this.options.inline = inline; return this; }

  target(target) {
    this.options.target = typeof target === 'string' ? document.querySelector(target) : target;
    this.caretKnown = false;

    if (this.options.target && this.root) {
      this.watchTarget();
    }

    return this;
  }

  /**
   * A field nobody has focused reports its caret at 0, so the first pick would land before text already in
   * it. The caret is trusted once the field has had focus; until then, picks append.
   */
  watchTarget() {
    const target = this.options.target;

    this.listen(target, 'focus', () => {
      this.caretKnown = true;
    });
    this.caretKnown = document.activeElement === target;
  }

  history({ max, store, order } = {}) {
    if (max !== undefined) this.options.maxRecent = max;
    if (store !== undefined) this.options.store = store;
    if (order !== undefined) this.options.recentOrder = order;

    return this;
  }

  on(event, handler) {
    this.handlers.set(event, [...(this.handlers.get(event) ?? []), handler]);

    return this;
  }

  get strings() {
    return { ...STRINGS, ...this.options.strings };
  }

  get store() {
    this.options.store ??= localStorageStore(this.options.userKey ? `${PREFIX}:${this.options.userKey}` : PREFIX);

    return this.options.store;
  }

  /** Builds the UI and loads the payload. Resolves once the emoji are drawn. */
  async mount() {
    if (this.element[MOUNTED]) {
      return this;
    }

    this.element[MOUNTED] = this;
    this.options.tone = this.store.get('tone') ?? this.options.tone;
    this.buildShell();

    try {
      const source = this.options.source ?? new StaticSource({ groups: [], custom: [] });

      this.data = await source.load(this.options.locale);
      this.applyVersionCap();
      this.render();
      this.emit('ready', { picker: this });
    } catch (error) {
      this.status.textContent = this.strings.failed;
      this.emit('error', { error });
    }

    return this;
  }

  destroy() {
    for (const undo of this.cleanup.splice(0)) {
      undo();
    }

    this.root?.remove();
    this.trigger?.remove();
    delete this.element[MOUNTED];
    this.handlers.clear();
  }

  open() {
    this.panel.hidden = false;
    this.trigger?.setAttribute('aria-expanded', 'true');
    this.searchInput.focus();
  }

  close() {
    if (this.options.inline) {
      return;
    }

    this.panel.hidden = true;
    this.trigger?.setAttribute('aria-expanded', 'false');
    this.trigger?.focus();
  }

  // ---- building ----

  listen(node, type, handler) {
    node.addEventListener(type, handler);
    this.cleanup.push(() => node.removeEventListener(type, handler));
  }

  emit(name, detail) {
    for (const handler of this.handlers.get(name) ?? []) {
      handler(detail);
    }
  }

  buildShell() {
    const s = this.strings;
    const id = `${PREFIX}-picker-${Math.random().toString(36).slice(2, 9)}`;

    this.root = el('div', { class: `${PREFIX}-picker` });
    this.root.style.setProperty(`--${PREFIX}-picker-columns`, String(this.options.columns));

    if (!this.options.inline) {
      this.trigger = el('button', { type: 'button', class: `${PREFIX}-picker-trigger`, 'aria-haspopup': 'dialog', 'aria-expanded': 'false', 'aria-controls': id, 'aria-label': s.open }, '🙂');
      this.listen(this.trigger, 'click', () => (this.panel.hidden ? this.open() : this.close()));
      this.root.append(this.trigger);
    }

    this.panel = el('div', { class: `${PREFIX}-picker-panel`, id, role: 'dialog', 'aria-label': s.open, hidden: !this.options.inline });
    this.searchInput = el('input', { type: 'search', class: `${PREFIX}-picker-search`, placeholder: s.search, 'aria-label': s.search, autocomplete: 'off', spellcheck: 'false' });
    this.tabs = el('div', { class: `${PREFIX}-picker-tabs`, role: 'tablist' });
    this.tones = el('div', { class: `${PREFIX}-picker-tones`, role: 'radiogroup', 'aria-label': s.tone });
    this.body = el('div', { class: `${PREFIX}-picker-body` });
    this.status = el('div', { class: `${PREFIX}-picker-status`, role: 'status', 'aria-live': 'polite' }, s.loading);

    this.panel.append(this.searchInput, this.tabs, this.body, this.tones, this.status);
    this.root.append(this.panel);
    this.element.replaceChildren(this.root);

    if (this.options.target) {
      this.watchTarget();
    }

    this.listen(this.searchInput, 'input', () => {
      this.query = this.searchInput.value;
      this.renderBody();
    });
    this.listen(this.panel, 'keydown', (event) => this.onKey(event));
    this.listen(this.body, 'click', (event) => {
      const cell = event.target.closest?.(`[${ATTR}-hexcode], [${ATTR}-custom]`);

      if (cell) {
        this.select(cell);
      }
    });
  }

  /** Drops emoji this platform would draw as empty boxes, before anything is rendered or indexed. */
  applyVersionCap() {
    const cap = this.options.maxVersion === 'auto' ? detectMaxVersion() : this.options.maxVersion;

    if (!cap) {
      return;
    }

    this.data = {
      ...this.data,
      groups: (this.data.groups ?? []).map((group) => ({ ...group, emoji: group.emoji.filter((item) => byVersion(item.version, cap) <= 0) })),
    };
  }

  render() {
    this.renderTabs();
    this.renderTones();
    this.renderBody();
  }

  sections() {
    const { categories } = this.options;
    const wanted = (slug) => categories.length === 0 || categories.includes(slug);
    const sections = [];
    const recents = orderRecent(this.store.get('recent') ?? [], this.options.recentOrder);
    const byHex = this.index();

    if (wanted('recent') && recents.length > 0) {
      sections.push({ slug: 'recent', label: this.strings.recent, items: recents.map((r) => byHex.get(r.base)).filter(Boolean) });
    }

    for (const group of this.data.groups ?? []) {
      if (wanted(group.slug)) {
        sections.push({ slug: group.slug, label: group.label, items: sortItems(group.emoji, this.options.sort) });
      }
    }

    if (wanted('custom') && (this.data.custom ?? []).length > 0) {
      sections.push({ slug: 'custom', label: this.strings.custom, custom: true, items: this.data.custom });
    }

    return sections;
  }

  index() {
    this.byHex ??= new Map((this.data.groups ?? []).flatMap((g) => g.emoji.map((item) => [item.hexcode, item])));

    return this.byHex;
  }

  renderTabs() {
    const tabs = this.sections().map((section) => {
      const first = section.custom ? null : section.items[0];
      const tab = el('button', { type: 'button', role: 'tab', class: `${PREFIX}-picker-tab`, title: section.label, 'aria-label': section.label, [`${ATTR}-section`]: section.slug }, first ? charOf(first.hexcode) : '★');

      this.listen(tab, 'click', () => {
        this.query = '';
        this.searchInput.value = '';
        this.renderBody();
        this.body.querySelector(`[${ATTR}-section="${section.slug}"]`)?.scrollIntoView?.({ block: 'start' });
      });

      return tab;
    });

    this.tabs.replaceChildren(...tabs);
  }

  renderTones() {
    const s = this.strings;
    const tones = ['✋', '✋🏻', '✋🏼', '✋🏽', '✋🏾', '✋🏿'].map((hand, tone) => {
      const radio = el('button', { type: 'button', role: 'radio', class: `${PREFIX}-picker-tone`, 'aria-checked': String(tone === this.options.tone), 'aria-label': s.tones[tone], tabindex: tone === this.options.tone ? '0' : '-1' }, hand);

      this.listen(radio, 'click', () => {
        this.options.tone = tone;
        this.store.set('tone', tone);
        this.renderTones();
        this.renderBody();
      });

      return radio;
    });

    this.tones.replaceChildren(...tones);
  }

  renderBody() {
    const s = this.strings;
    const term = this.query.trim();
    const sections = term
      ? [{ slug: 'search', label: s.search, items: search(this.sections().filter((x) => !x.custom && x.slug !== 'recent').flatMap((x) => x.items), term) }]
      : this.sections();
    const nodes = [];
    let count = 0;

    for (const section of sections) {
      if (section.items.length === 0) {
        continue;
      }

      const heading = el('div', { class: `${PREFIX}-picker-heading`, id: `${this.panel.id}-${section.slug}` }, section.label);
      const grid = el('div', { class: `${PREFIX}-picker-grid`, role: 'grid', 'aria-labelledby': heading.id });
      let row = null;

      section.items.forEach((item, index) => {
        if (index % this.options.columns === 0) {
          row = el('div', { role: 'row', class: `${PREFIX}-picker-row` });
          grid.append(row);
        }

        row.append(section.custom ? this.customCell(item) : this.cell(item));
        count++;
      });

      const block = el('section', { class: `${PREFIX}-picker-section`, [`${ATTR}-section`]: section.slug });

      block.append(heading, grid);
      nodes.push(block);
    }

    this.body.replaceChildren(...nodes);
    this.status.textContent = term ? (count === 0 ? s.noResults : s.results.replace('{count}', String(count))) : '';

    const first = this.body.querySelector('[role="gridcell"]');

    if (first) {
      first.tabIndex = 0;
      this.active = first;
    }
  }

  cell(item) {
    const hexcode = withTone(item, this.options.tone);

    return el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell`, title: item.name, 'aria-label': item.name, [`${ATTR}-hexcode`]: hexcode, [`${ATTR}-base`]: item.hexcode }, charOf(hexcode));
  }

  customCell(item) {
    const button = el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell`, title: item.label, 'aria-label': item.label, [`${ATTR}-custom`]: item.name });
    const image = el('img', { src: item.image, alt: '', class: `${PREFIX} ${PREFIX}-image`, draggable: 'false', loading: 'lazy' });

    button.append(image);

    return button;
  }

  // ---- interaction ----

  onKey(event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.close();

      return;
    }

    const cell = event.target.closest?.('[role="gridcell"]');

    if (!cell) {
      if (event.key === 'ArrowDown' && event.target === this.searchInput) {
        event.preventDefault();
        this.focusCell(this.body.querySelector('[role="gridcell"]'));
      }

      return;
    }

    const cells = [...this.body.querySelectorAll('[role="gridcell"]')];
    const index = cells.indexOf(cell);
    const step = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: this.options.columns, ArrowUp: -this.options.columns }[event.key];

    if (step !== undefined) {
      event.preventDefault();

      if (index + step < 0 && event.key === 'ArrowUp') {
        this.searchInput.focus();

        return;
      }

      this.focusCell(cells[Math.max(0, Math.min(cells.length - 1, index + step))]);
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      this.focusCell(event.key === 'Home' ? cells[0] : cells[cells.length - 1]);
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      this.select(cell);
    }
  }

  focusCell(cell) {
    if (!cell) {
      return;
    }

    if (this.active) {
      this.active.tabIndex = -1;
    }

    cell.tabIndex = 0;
    cell.focus();
    this.active = cell;
  }

  select(cell) {
    const name = cell.getAttribute(`${ATTR}-custom`);
    let detail;

    if (name !== null) {
      const custom = (this.data.custom ?? []).find((c) => c.name === name);
      detail = { emoji: `:${name}:`, hexcode: null, name: custom?.label ?? name, shortcode: name, custom: true };
    } else {
      const hexcode = cell.getAttribute(`${ATTR}-hexcode`);
      const base = cell.getAttribute(`${ATTR}-base`) ?? hexcode;
      const item = this.index().get(base);

      detail = { emoji: charOf(hexcode), hexcode, name: item?.name ?? '', shortcode: item?.shortcode ?? null, custom: false };
      this.store.set('recent', recordRecent(this.store.get('recent') ?? [], base, hexcode, this.options.maxRecent));
    }

    this.insert(detail.emoji);
    this.element.dispatchEvent(new CustomEvent(EVENT, { detail, bubbles: true }));
    this.emit('select', detail);

    if (this.options.closeOnSelect) {
      this.close();
    }
  }

  /** Inserts at the caret of the target input or textarea and dispatches `input`, so frameworks see it. */
  insert(text) {
    const target = this.options.target;

    if (!target || !('value' in target)) {
      return;
    }

    const caret = this.caretKnown || document.activeElement === target;
    const start = caret ? (target.selectionStart ?? target.value.length) : target.value.length;
    const end = caret ? (target.selectionEnd ?? target.value.length) : target.value.length;

    target.value = target.value.slice(0, start) + text + target.value.slice(end);
    target.setSelectionRange?.(start + text.length, start + text.length);
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }
}

// ---- auto-init -------------------------------------------------------------------------------------------

/** Mounts a picker from its data-laranail-emoji-* attributes. Idempotent: a mounted element is left alone. */
export function mountElement(element) {
  if (element[MOUNTED]) {
    return element[MOUNTED];
  }

  const options = parseOptions(element);
  const source = options.payload ? new StaticSource(options.payload) : options.source ? new ApiSource(options.source) : undefined;
  const picker = Picker.create(element, { ...options, target: undefined, source });

  if (options.target) {
    picker.target(options.target);
  }

  picker.mount();

  return picker;
}

/**
 * Mounts every [data-laranail-emoji-picker] under root now and as they are added later, with one
 * MutationObserver. Returns a function that stops observing.
 */
export function autoInit(root = document) {
  const selector = `[${ATTR}-picker]`;
  const scan = (node) => {
    if (node.nodeType !== 1) return;
    if (node.matches(selector)) mountElement(node);
    node.querySelectorAll(selector).forEach(mountElement);
  };

  scan(root.documentElement ?? root);

  if (typeof MutationObserver === 'undefined') {
    return () => {};
  }

  const observer = new MutationObserver((records) => records.forEach((r) => r.addedNodes.forEach(scan)));
  observer.observe(root.documentElement ?? root, { childList: true, subtree: true });

  return () => observer.disconnect();
}

// Importing the module mounts every picker on the page. Set globalThis.__laranailEmojiNoAutoInit = true
// before importing to mount by hand instead.
if (typeof document !== 'undefined' && !globalThis.__laranailEmojiNoAutoInit) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => autoInit(), { once: true });
  } else {
    autoInit();
  }
}
