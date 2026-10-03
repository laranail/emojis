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
 *
 * The state — search, sections, recents, tones, the version cap, inserting — is pure functions exported
 * below, which this vanilla Picker and the React adapter (resources/assets/react) both use.
 */

// ---- types --------------------------------------------------------------------------------------------

export interface PickerEmoji {
  emoji: string;
  hexcode: string;
  name: string;
  shortcode: string | null;
  keywords: string[];
  version: string;
  skins: Record<string, string>;
  /** The Emoji version of the variants newer than the base: one for all of them, or per tone key. */
  skin_versions?: string | Record<string, string>;
  /** False when the policy refuses the emoji itself but permits some of its toned forms. */
  base?: false;
  /** Set on a Frequently used entry: the exact form that was picked, which the cell shows whatever the tone. */
  pick?: string;
}

export interface PickerGroup {
  slug: string;
  label: string;
  emoji: PickerEmoji[];
}

export interface PickerCustom {
  name: string;
  label: string;
  image: string;
  fallback: string | null;
}

/** The payload GET /picker serves and PayloadBuilder builds. */
export interface PickerPayload {
  dataset?: string;
  locale?: string;
  groups: PickerGroup[];
  custom?: PickerCustom[];
  /** The configured shortcode delimiters a custom emoji is inserted with; [':', ':'] when absent. */
  delimiters?: [string, string];
}

export interface PickerSource {
  /** `signal` aborts the load when the picker is destroyed first; sources may ignore it. */
  load(locale?: string | null, signal?: AbortSignal): Promise<PickerPayload>;
}

export interface PickerStore {
  get(key: string): unknown;
  set(key: string, value: unknown): void;
}

export interface SelectDetail {
  emoji: string;
  hexcode: string | null;
  name: string;
  shortcode: string | null;
  custom: boolean;
}

export interface RecentEntry {
  base: string;
  hexcode: string;
  count: number;
  at: number;
}

export type SortMode = 'default' | 'name' | 'newest';
export type RecentOrder = 'recent' | 'frequent';

export interface PickerStrings {
  search: string;
  results: string;
  /** The count when it is exactly one ("1 result"); `results` is used when absent. */
  resultsOne?: string;
  noResults: string;
  recent: string;
  custom: string;
  tone: string;
  tones: string[];
  open: string;
  loading: string;
  failed: string;
}

export interface PickerOptions {
  source?: PickerSource;
  /** An input, a textarea, or a contenteditable element. */
  target?: Insertable | null;
  locale?: string | null;
  tone?: number;
  maxRecent?: number;
  recentOrder?: RecentOrder;
  sort?: SortMode;
  categories?: string[];
  columns?: number;
  /** Hide emoji newer than this Emoji version; 'auto' (default) detects what the browser draws, null or '' shows all. */
  maxVersion?: string | null;
  closeOnSelect?: boolean;
  inline?: boolean;
  userKey?: string;
  store?: PickerStore;
  strings?: Partial<PickerStrings>;
  /** Milliseconds to wait after the last keystroke before searching (default 80). */
  searchDelay?: number;
  /** What the trigger button shows (default 🙂). */
  trigger?: string;
  /** Where the popover opens: 'auto' (below, flipping above), or top/bottom/start/end, optionally -start/-end. */
  placement?: Placement;
  /** Gap between the trigger and the popover, in px (default 8). */
  offset?: number;
  /** Draw the caret pointing at the trigger (default true). */
  arrow?: boolean;
  /** At or below this viewport width, in CSS px, the popover is a bottom sheet (default 640; 0 never). */
  sheetBreakpoint?: number;
}

export interface PickerEvents {
  select: SelectDetail;
  ready: { picker: Picker };
  error: { error: unknown };
}

/** One rendered block: Frequently used, a Unicode group, or the custom emoji. */
export type PickerSection =
  | { slug: string; label: string; custom?: false; items: PickerEmoji[] }
  | { slug: string; label: string; custom: true; items: PickerCustom[] };

export interface ParsedOptions {
  target: string | null;
  source: string | null;
  payload: string | null;
  locale: string | null;
  tone: number;
  maxRecent: number;
  recentOrder: RecentOrder;
  sort: SortMode;
  categories: string[];
  columns: number;
  maxVersion: string;
  closeOnSelect: boolean;
  inline: boolean;
  userKey: string;
  strings: Partial<PickerStrings>;
  trigger: string;
  placement: Placement;
  offset: number;
  arrow: boolean;
  sheetBreakpoint: number;
}

/** Where a pick can be inserted: an input, a textarea, or a contenteditable element. */
export type Insertable = HTMLInputElement | HTMLTextAreaElement | HTMLElement;

/**
 * Build-time switch for the import-time auto-init at the bottom of this module. Undefined (the default build
 * and tests) means on; the React bundle (vite.react.config.mjs) defines it false, which compiles the auto-init,
 * and the DOM Picker that only it uses, out of that bundle.
 */
declare const __LARANAIL_EMOJI_AUTO_INIT__: boolean | undefined;

const PREFIX = 'laranail-emoji';
const ATTR = `data-${PREFIX}`;
const EVENT = `${PREFIX}:select`;
const MOUNTED = Symbol.for('laranail-emoji-picker');

type Mountable = HTMLElement & { [MOUNTED]?: Picker };

/** The interface strings in English; every one can be replaced through the `strings` option. */
export const DEFAULT_STRINGS: PickerStrings = {
  search: 'Search emoji',
  results: '{count} results',
  resultsOne: '1 result',
  noResults: 'No emoji found',
  recent: 'Frequently used',
  custom: 'Custom',
  tone: 'Skin tone',
  tones: ['Default', 'Light', 'Medium-light', 'Medium', 'Medium-dark', 'Dark'],
  open: 'Choose an emoji',
  loading: 'Loading emoji',
  failed: 'Emoji could not be loaded',
};

/** The hand shown for each tone choice, 0 (default) to 5. */
export const TONE_SWATCHES: readonly string[] = ['✋', '✋🏻', '✋🏼', '✋🏽', '✋🏾', '✋🏿'];

// ---- pure helpers ---------------------------------------------------------------------------------------

/** Lower-case and strip diacritics, so "fusee" finds "fusée" and "CAFE" finds "café". */
export function fold(text: string): string {
  return String(text).normalize('NFD').replace(/\p{M}+/gu, '').toLowerCase().trim();
}

/** "1F44B-1F3FD" → "👋🏽". */
export function charOf(hexcode: string): string {
  return hexcode.split('-').map((part) => String.fromCodePoint(parseInt(part, 16))).join('');
}

/**
 * The hexcode an emoji takes with a skin tone (1–5), or its own when it takes none. An emoji whose base the
 * policy refuses (`base: false`) falls back to its first permitted toned form, never to the base.
 */
export function withTone(item: PickerEmoji, tone: number): string {
  const skins = item.skins ?? {};
  // One person: "3". Several (🤝, 💏): the same tone on each, "3-3".
  const toned = tone ? (skins[String(tone)] ?? skins[`${tone}-${tone}`]) : undefined;

  if (toned) {
    return toned;
  }

  return item.base === false ? (Object.values(skins)[0] ?? item.hexcode) : item.hexcode;
}

/** The text a custom emoji is inserted as: its name between the payload's shortcode delimiters. */
export function customCode(name: string, data?: Pick<PickerPayload, 'delimiters'> | null): string {
  const [open, close] = data?.delimiters ?? [':', ':'];

  return `${open}${name}${close}`;
}

/** Compares two dotted Emoji versions ("15.1" > "15.0"). */
export function byVersion(a: string, b: string): number {
  const [am = 0, an = 0] = String(a).split('.').map(Number);
  const [bm = 0, bn = 0] = String(b).split('.').map(Number);

  return am - bm || an - bn;
}

/**
 * One emoji per Emoji version, newest first, to find what this platform draws. ZWJ sequences stand for the
 * versions that added only sequences, since a platform without them draws two glyphs side by side.
 */
const VERSION_SAMPLES: ReadonlyArray<readonly [string, string]> = [
  ['18.0', '\u{1FAEB}'], ['17.0', '\u{1FAEA}'], ['16.0', '\u{1FAE9}'], ['15.1', '\u{1F642}‍↔️'],
  ['15.0', '\u{1FAE8}'], ['14.0', '\u{1FAE0}'], ['13.1', '\u{1F62E}‍\u{1F4A8}'], ['13.0', '\u{1F972}'],
  ['12.1', '\u{1F9D1}‍\u{1F9B0}'], ['12.0', '\u{1F971}'], ['11.0', '\u{1F970}'],
];

/**
 * The newest Emoji version this browser draws, or null when it cannot tell — no canvas (tests, old
 * browsers), a canvas that adds noise against fingerprinting, or no colour emoji font at all — and then
 * nothing is hidden.
 *
 * A sample counts as drawn when it comes out in colour and as one glyph. Comparing against a "tofu" box
 * is not enough: macOS's LastResort font draws a different placeholder per Unicode block, so a missing
 * emoji never matched the probe and everything read as supported. Colour sidesteps that: placeholders and
 * fallback text are drawn in the fill colour (black), emoji fonts are not. A sequence the font lacks draws
 * as its parts, wider than one emoji.
 */
export function detectMaxVersion(doc: Pick<Document, 'createElement'> | undefined = globalThis.document): string | null {
  const canvas = doc?.createElement?.('canvas') as HTMLCanvasElement | undefined;
  const context = canvas?.getContext?.('2d', { willReadFrequently: true }) as CanvasRenderingContext2D | null | undefined;

  if (!canvas || !context || typeof context.getImageData !== 'function') {
    return null;
  }

  const size = 24;
  canvas.width = canvas.height = size * 2;
  context.font = `${size}px 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji','Twemoji Mozilla','Android Emoji',sans-serif`;
  context.textBaseline = 'top';
  context.fillStyle = '#000';

  const pixels = (text: string): ArrayLike<number> => {
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.fillText(text, 0, 0);

    return context.getImageData(0, 0, canvas.width, canvas.height).data;
  };
  const colourful = (text: string): boolean => {
    const data = pixels(text);

    for (let i = 0; i + 3 < data.length; i += 4) {
      const [r = 0, g = 0, b = 0, a = 0] = [data[i], data[i + 1], data[i + 2], data[i + 3]];

      if (a > 0 && Math.max(Math.abs(r - g), Math.abs(g - b), Math.abs(r - b)) > 48) {
        return true;
      }
    }

    return false;
  };

  // A canvas that randomises its output reports colour where nothing was drawn: it cannot be trusted.
  if (colourful('')) {
    return null;
  }

  const single = context.measureText('\u{1F600}').width;

  for (const [version, sample] of VERSION_SAMPLES) {
    if (colourful(sample) && context.measureText(sample).width < single * 1.5) {
      return version;
    }
  }

  return null;
}

let detected: Promise<string | null> | null = null;

/**
 * detectMaxVersion() once per page, after web fonts have loaded: a page whose emoji font is a web font
 * (Noto Color Emoji from Google Fonts) would otherwise be measured before the font arrives.
 */
export function detectMaxVersionOnce(): Promise<string | null> {
  detected ??= (async () => {
    try {
      await (globalThis.document as Document | undefined)?.fonts?.ready;
    } catch {
      // No font loading API: measure now.
    }

    return detectMaxVersion();
  })();

  return detected;
}

/**
 * The payload without emoji newer than `cap` ('auto' asks the browser; null or '' keeps everything). Toned
 * forms are capped on their own version, since they can be newer than their base (🤝 is 3.0, its tones
 * 14.0): a capped tone is dropped from `skins`, and the emoji then falls back to its untoned form.
 */
export function capPayload(data: PickerPayload, cap: string | null | undefined, detect: () => string | null = detectMaxVersion): PickerPayload {
  const version = cap === 'auto' ? detect() : cap;

  if (!version) {
    return data;
  }

  const fits = (v: string): boolean => byVersion(v, version) <= 0;
  const trim = (item: PickerEmoji): PickerEmoji => {
    const versions = item.skin_versions;

    if (!versions || !item.skins) {
      return item;
    }

    const skins = Object.fromEntries(Object.entries(item.skins).filter(([key]) => fits(typeof versions === 'string' ? versions : (versions[key] ?? item.version))));

    return { ...item, skins };
  };

  return {
    ...data,
    groups: (data.groups ?? []).map((group) => ({
      ...group,
      emoji: group.emoji.filter((item) => fits(item.version)).map(trim).filter((item) => item.base !== false || Object.keys(item.skins ?? {}).length > 0),
    })),
  };
}

/**
 * Ranks emoji for a search term: exact name, name prefix, shortcode, keyword prefix, anywhere. Every word
 * of the term must match somewhere.
 */
export function search(items: PickerEmoji[], term: string, limit: number = Infinity): PickerEmoji[] {
  const glyph = bare(term.trim());

  // A pasted emoji finds itself, toned or not.
  if (glyph !== '' && /\p{Extended_Pictographic}|\p{Regional_Indicator}/u.test(glyph)) {
    return items.filter((item) => [item.hexcode, ...Object.values(item.skins ?? {})].some((hex) => bare(charOf(hex)) === glyph)).slice(0, limit);
  }

  // ":smile", ":smile:" and "smile" are the same search.
  const words = fold(term).split(/\s+/).map((word) => word.replace(/^:+|:+$/g, '')).filter(Boolean);

  if (words.length === 0) {
    return [];
  }

  const phrase = words.join(' ');
  const scored: Array<[number, number, PickerEmoji]> = [];

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
      : keywords.some((k) => k.startsWith(words[0] ?? '')) ? 3
      : 4;

    scored.push([score, scored.length, item]);
  }

  return scored.sort((a, b) => a[0] - b[0] || a[1] - b[1]).slice(0, limit).map(([, , item]) => item);
}

/** A string without variation selectors, so 😶‍🌫️ and 😶‍🌫 compare equal. */
function bare(text: string): string {
  return text.replace(/\uFE0F|\uFE0E/g, '');
}

/** "No emoji found", "1 result", "12 results". */
export function resultText(strings: Pick<PickerStrings, 'noResults' | 'results' | 'resultsOne'>, count: number): string {
  if (count === 0) {
    return strings.noResults;
  }

  return (count === 1 && strings.resultsOne ? strings.resultsOne : strings.results).replace('{count}', String(count));
}

/** The custom emoji whose name or label matches every word of a term, name prefix first. */
export function searchCustom(items: PickerCustom[], term: string): PickerCustom[] {
  const words = fold(term).split(/\s+/).map((word) => word.replace(/^:+|:+$/g, '')).filter(Boolean);

  if (words.length === 0) {
    return [];
  }

  return items
    .filter((item) => words.every((word) => `${fold(item.name)} ${fold(item.label)}`.includes(word)))
    .sort((a, b) => Number(!fold(b.name).startsWith(words[0] ?? '')) - Number(!fold(a.name).startsWith(words[0] ?? '')));
}

/** A tone from anywhere (an attribute, storage, a prop): 0–5, and 0 for anything else. */
export function clampTone(value: unknown): number {
  const tone = typeof value === 'number' || typeof value === 'string' ? Math.trunc(Number(value)) : NaN;

  return Number.isFinite(tone) && tone >= 0 && tone <= 5 ? tone : 0;
}

/** A column count: a whole number from 1 to 24, else the fallback. */
export function clampColumns(value: unknown, fallback = 8): number {
  const columns = typeof value === 'number' || typeof value === 'string' ? Math.trunc(Number(value)) : NaN;

  return Number.isFinite(columns) && columns >= 1 ? Math.min(columns, 24) : fallback;
}

/**
 * Recents as read back from storage: only well-formed entries. A corrupt value or one an older or newer
 * version wrote reads as no recents, instead of breaking the picker until storage is cleared.
 */
export function readRecent(value: unknown): RecentEntry[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.flatMap((entry): RecentEntry[] => {
    const e = entry as Partial<RecentEntry> | null;

    if (typeof e !== 'object' || e === null || typeof e.base !== 'string') {
      return [];
    }

    const count = typeof e.count === 'number' && Number.isFinite(e.count) ? e.count : 1;
    const at = typeof e.at === 'number' && Number.isFinite(e.at) ? e.at : 0;

    return [{ base: e.base, hexcode: typeof e.hexcode === 'string' ? e.hexcode : e.base, count, at }];
  });
}

/** Orders a list: "default" keeps CLDR order, "name" sorts by name, "newest" puts the latest Emoji version first. */
export function sortItems(items: PickerEmoji[], mode: SortMode): PickerEmoji[] {
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
 * `max` entries. Returns a new list.
 */
export function recordRecent(stored: unknown, base: string, hexcode: string, max: number, now: number = Date.now()): RecentEntry[] {
  const list = readRecent(stored);
  const previous = list.find((entry) => entry.base === base);
  const entry = { base, hexcode, count: (previous?.count ?? 0) + 1, at: now };

  return [entry, ...list.filter((e) => e.base !== base)].slice(0, Math.max(0, max));
}

/** Orders recents: "recent" by time of last use, "frequent" by count then time. */
export function orderRecent(list: RecentEntry[], mode: RecentOrder): RecentEntry[] {
  return [...readRecent(list)].sort((a, b) => (mode === 'frequent' ? b.count - a.count || b.at - a.at : b.at - a.at));
}

/** Every emoji of the payload by its base hexcode. */
export function indexPayload(data: PickerPayload): Map<string, PickerEmoji> {
  return new Map((data.groups ?? []).flatMap((group) => group.emoji.map((item): [string, PickerEmoji] => [item.hexcode, item])));
}

/**
 * What the picker shows when nothing is searched: Frequently used, then each group, then Custom — limited to
 * `categories` when given. Recents whose emoji the payload no longer has (a capped version) drop out.
 */
export function buildSections(
  data: PickerPayload,
  options: { categories?: string[]; sort?: SortMode; recent?: RecentEntry[]; recentOrder?: RecentOrder; strings?: Partial<PickerStrings> } = {},
): PickerSection[] {
  const categories = options.categories ?? [];
  const strings = { ...DEFAULT_STRINGS, ...options.strings };
  const wanted = (slug: string): boolean => categories.length === 0 || categories.includes(slug);
  const byHex = indexPayload(data);
  // A recent keeps the form that was picked (👋🏽), unless the policy or a version cap no longer offers it.
  const recents = orderRecent(options.recent ?? [], options.recentOrder ?? 'recent')
    .map((r): PickerEmoji | undefined => {
      const item = byHex.get(r.base);
      const offered = item && ((r.hexcode === item.hexcode && item.base !== false) || Object.values(item.skins ?? {}).includes(r.hexcode));

      return item && offered ? { ...item, pick: r.hexcode } : item;
    })
    .filter((item): item is PickerEmoji => item !== undefined);
  const sections: PickerSection[] = [];

  if (wanted('recent') && recents.length > 0) {
    sections.push({ slug: 'recent', label: strings.recent, items: recents });
  }

  for (const group of data.groups ?? []) {
    if (wanted(group.slug)) {
      sections.push({ slug: group.slug, label: group.label, items: sortItems(group.emoji, options.sort ?? 'default') });
    }
  }

  if (wanted('custom') && (data.custom ?? []).length > 0) {
    sections.push({ slug: 'custom', label: strings.custom, custom: true, items: data.custom ?? [] });
  }

  return sections;
}

/** The search results over a list of sections: the emoji of every non-custom group, ranked. */
export function searchSections(sections: PickerSection[], term: string, limit: number = Infinity): PickerEmoji[] {
  return search(sections.filter((s): s is Extract<PickerSection, { custom?: false }> => !s.custom && s.slug !== 'recent').flatMap((s) => s.items), term, limit);
}

/** The most results a search draws; a one-letter term would otherwise draw nearly every emoji. */
export const MAX_RESULTS = 200;

/**
 * What a search shows: the ranked emoji, then the matching custom emoji, as sections. Shared by both
 * pickers so a term finds the same things in each.
 */
export function searchResults(sections: PickerSection[], term: string, strings: Pick<PickerStrings, 'search' | 'custom'>, limit: number = MAX_RESULTS): PickerSection[] {
  const custom = sections.find((s): s is Extract<PickerSection, { custom: true }> => s.custom === true);
  const out: PickerSection[] = [{ slug: 'search', label: strings.search, items: searchSections(sections, term, limit) }];
  const customHits = custom ? searchCustom(custom.items, term) : [];

  if (customHits.length > 0) {
    out.push({ slug: 'search-custom', label: strings.custom, custom: true, items: customHits });
  }

  return out;
}

/**
 * The cell a grid key moves focus to, read from the rendered rows so both pickers share it: arrows keep
 * the column across rows and sections (clamped to a shorter row), Left and Right swap under RTL,
 * PageUp/PageDown jump a section, Home/End go to the first and last cell. 'search' means ArrowUp left the
 * first row. null means the key is not a grid key, or there is nowhere to go.
 */
export function gridTarget(body: ParentNode, cell: Element, key: string, rtl = false): HTMLElement | 'search' | null {
  const rows = [...body.querySelectorAll<HTMLElement>('[role="row"]')];
  const cellsOf = (row: Element | undefined): HTMLElement[] => (row ? [...row.querySelectorAll<HTMLElement>('[role="gridcell"]')] : []);
  const row = cell.closest('[role="row"]');
  const r = row ? rows.indexOf(row as HTMLElement) : -1;
  const c = cellsOf(row ?? undefined).indexOf(cell as HTMLElement);
  const all = rows.flatMap((x) => cellsOf(x));
  const at = (target: Element | undefined): HTMLElement | null => {
    const cells = cellsOf(target);

    return cells[Math.min(c, cells.length - 1)] ?? null;
  };

  if (r < 0) {
    return null;
  }

  switch (key) {
    case 'ArrowRight':
    case 'ArrowLeft': {
      const step = (key === 'ArrowRight') !== rtl ? 1 : -1;

      return all[all.indexOf(cell as HTMLElement) + step] ?? null;
    }
    case 'ArrowDown':
      return at(rows[r + 1]);
    case 'ArrowUp':
      return r === 0 ? 'search' : at(rows[r - 1]);
    case 'PageDown':
    case 'PageUp': {
      const sections = [...body.querySelectorAll('[role="grid"]')];
      const s = sections.indexOf(cell.closest('[role="grid"]') as Element);
      const next = sections[s + (key === 'PageDown' ? 1 : -1)];

      return next ? at(next.querySelector('[role="row"]') ?? undefined) : null;
    }
    case 'Home':
      return all[0] ?? null;
    case 'End':
      return all[all.length - 1] ?? null;
    default:
      return null;
  }
}

/**
 * The index a roving-tabindex key moves to in a row of tabs or radios: arrows wrap, Left and Right swap
 * under RTL, Home and End go to the ends. null for any other key.
 */
export function rovingIndex(count: number, current: number, key: string, rtl = false): number | null {
  if (count <= 0) {
    return null;
  }

  const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
  const back = rtl ? 'ArrowRight' : 'ArrowLeft';

  if (key === forward || key === 'ArrowDown') return (current + 1) % count;
  if (key === back || key === 'ArrowUp') return (current - 1 + count) % count;
  if (key === 'Home') return 0;
  if (key === 'End') return count - 1;

  return null;
}

/**
 * Inserts text into an input, a textarea or a contenteditable element, and dispatches `input` and
 * `change`, so frameworks see it (wire:model.change and x-model.lazy listen for change). In a field at the
 * caret when `caretKnown` (the field has had focus); otherwise at the end, because a field nobody has
 * focused reports its caret at 0 and the text would land before what is already there. Returns false, and
 * changes nothing, when the text would take the field past its maxlength.
 */
export function insertText(target: Insertable, text: string, caretKnown: boolean): boolean {
  if (!('value' in target) || typeof target.value !== 'string') {
    return insertEditable(target, text);
  }

  const field = target as HTMLInputElement | HTMLTextAreaElement;
  const caret = caretKnown || field.ownerDocument?.activeElement === field;
  const length = field.value.length;
  const start = caret ? (selection(field, 'selectionStart') ?? length) : length;
  const end = caret ? (selection(field, 'selectionEnd') ?? length) : length;
  const next = field.value.slice(0, start) + text + field.value.slice(end);

  if (field.maxLength > 0 && next.length > field.maxLength) {
    return false;
  }

  // Write through the prototype's setter, not the element's own: a framework that tracks the value by
  // shadowing that setter (React's controlled inputs) then still holds the old value, sees the change on the
  // input event and keeps it, instead of writing its stale state back over the pick.
  const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field) as object, 'value')?.set;

  if (setter) {
    setter.call(field, next);
  } else {
    field.value = next;
  }

  try {
    field.setSelectionRange(start + text.length, start + text.length);
  } catch {
    // type=email and type=number have no selection API.
  }

  field.dispatchEvent(new Event('input', { bubbles: true }));
  field.dispatchEvent(new Event('change', { bubbles: true }));

  return true;
}

/**
 * Inserts into a contenteditable element (Trix, TipTap, a plain one) at its selection, or at the end when
 * the selection is elsewhere. execCommand keeps the editor's undo history and fires its own input event;
 * where it is unavailable a text node is inserted by hand.
 */
function insertEditable(target: HTMLElement, text: string): boolean {
  if (!target.isContentEditable) {
    return false;
  }

  const doc = target.ownerDocument;
  const selection = doc.getSelection();
  const inside = selection !== null && selection.rangeCount > 0 && target.contains(selection.getRangeAt(0).commonAncestorContainer);

  target.focus();

  if (!inside && selection) {
    const range = doc.createRange();
    range.selectNodeContents(target);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
  }

  if (typeof doc.execCommand === 'function' && doc.execCommand('insertText', false, text)) {
    return true;
  }

  const range = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
  const node = doc.createTextNode(text);

  if (range) {
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
  } else {
    target.append(node);
  }

  target.dispatchEvent(new Event('input', { bubbles: true }));

  return true;
}

/** selectionStart/End, or null on fields that throw for them (type=email, number). */
function selection(target: HTMLInputElement | HTMLTextAreaElement, key: 'selectionStart' | 'selectionEnd'): number | null {
  try {
    return target[key];
  } catch {
    return null;
  }
}

/** Reads every data-laranail-emoji-* option on an element, typed. Unknown attributes are ignored. */
export function parseOptions(element: Element): ParsedOptions {
  const data = (name: string): string | null => element.getAttribute(`${ATTR}-${name}`);
  const int = (name: string, fallback: number): number => {
    const value = Number.parseInt(data(name) ?? '', 10);

    return Number.isFinite(value) ? value : fallback;
  };
  const list = (name: string): string[] => (data(name) ?? '').split(',').map((v) => v.trim()).filter(Boolean);
  const strings = data('strings');
  const sort = data('sort');

  return {
    target: data('target'),
    source: data('source'),
    payload: data('payload'),
    locale: data('locale'),
    tone: clampTone(int('tone', 0)),
    maxRecent: int('max-recent', 36),
    recentOrder: data('recent-order') === 'frequent' ? 'frequent' : 'recent',
    sort: sort === 'name' || sort === 'newest' ? sort : 'default',
    categories: list('categories'),
    columns: clampColumns(int('columns', 8)),
    maxVersion: data('max-version') ?? 'auto',
    closeOnSelect: data('close-on-select') !== 'false',
    inline: element.hasAttribute(`${ATTR}-inline`),
    userKey: data('user-key') ?? '',
    strings: strings ? safeJson<Partial<PickerStrings>>(strings, {}) : {},
    trigger: data('trigger') ?? '🙂',
    placement: parsePlacement(data('placement')),
    offset: int('offset', 8),
    arrow: data('arrow') !== 'false',
    sheetBreakpoint: Math.max(0, int('sheet-breakpoint', 640)),
  };
}

const PLACEMENTS = new Set(['auto', 'top', 'bottom', 'start', 'end', 'top-start', 'top-end', 'bottom-start', 'bottom-end', 'start-start', 'start-end', 'end-start', 'end-end']);

/** A placement from an attribute or prop; anything unknown is 'auto'. */
export function parsePlacement(value: unknown): Placement {
  return typeof value === 'string' && PLACEMENTS.has(value) ? (value as Placement) : 'auto';
}

function safeJson<T>(text: string, fallback: T): T {
  try {
    return JSON.parse(text) as T;
  } catch {
    return fallback;
  }
}

// ---- sources and stores --------------------------------------------------------------------------------

/** Loads the payload from the package's API (`…/api/v1`) or straight from a `…/picker` URL. */
export class ApiSource implements PickerSource {
  readonly url: string;

  constructor(url: string, private readonly init: RequestInit = {}) {
    this.url = String(url).replace(/\/+$/, '');
  }

  /**
   * One request per URL and locale for every picker on the page: a thread with a reply picker per message
   * fetches the payload once instead of once each (and does not spend the API's rate limit doing it).
   */
  private static inflight = new Map<string, Promise<PickerPayload>>();

  load(locale?: string | null, signal?: AbortSignal): Promise<PickerPayload> {
    const key = `${this.url}\u0000${locale ?? ''}\u0000${JSON.stringify(this.init.headers ?? null)}`;
    let shared = ApiSource.inflight.get(key);

    if (!shared) {
      shared = this.fetch(locale);
      ApiSource.inflight.set(key, shared);
      // A failure is not cached: the next picker tries again.
      shared.catch(() => ApiSource.inflight.delete(key));
    }

    if (!signal) {
      return shared;
    }

    // Aborting one picker's load rejects its own promise, never the shared request others wait on.
    return new Promise((resolve, reject) => {
      const abort = (): void => reject(new DOMException('Aborted', 'AbortError'));

      if (signal.aborted) {
        abort();

        return;
      }

      signal.addEventListener('abort', abort, { once: true });
      shared.then(resolve, reject).finally(() => signal.removeEventListener('abort', abort));
    });
  }

  /** Forgets every shared request, so the next load fetches again (after a locale's data changed, or in tests). */
  static clear(): void {
    ApiSource.inflight.clear();
  }

  private async fetch(locale?: string | null): Promise<PickerPayload> {
    // The URL may carry its own query string (a signed URL, a tenant parameter): keep it.
    const [path = '', query = ''] = this.url.split('?', 2);
    const base = path.replace(/\/+$/, '');
    const endpoint = base.endsWith('/picker') ? base : `${base}/picker`;
    const params = new URLSearchParams(query);

    if (locale) {
      params.set('locale', locale);
    }

    const search = params.toString();
    // Merged, so headers passed in (a CSRF token, an auth header) do not drop Accept.
    const headers = new Headers(this.init.headers);

    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }

    const response = await fetch(search ? `${endpoint}?${search}` : endpoint, {
      credentials: 'same-origin',
      ...this.init,
      headers,
    });

    if (!response.ok) {
      throw new Error(`picker payload: HTTP ${response.status}`);
    }

    const body = (await response.json()) as { data?: PickerPayload } & PickerPayload;

    return body.data ?? body;
  }
}

/** A payload already in hand: an object, or the id of a <script type="application/json"> holding one. */
export class StaticSource implements PickerSource {
  constructor(private readonly payload: PickerPayload | string) {}

  async load(): Promise<PickerPayload> {
    if (typeof this.payload !== 'string') {
      return this.payload;
    }

    const block = document.getElementById(this.payload);

    if (!block) {
      throw new Error(`picker payload: no element #${this.payload}`);
    }

    return JSON.parse(block.textContent ?? '{}') as PickerPayload;
  }
}

/** A store that forgets on reload. */
export function memoryStore(): PickerStore {
  const map = new Map<string, unknown>();

  return { get: (key) => map.get(key) ?? null, set: (key, value) => void map.set(key, value) };
}

/**
 * localStorage, namespaced, and never fatal: private mode, a full quota or blocked storage fall back to
 * memory. Only hexcodes, counts and times are written — never text the user typed.
 */
export function localStorageStore(namespace: string = PREFIX): PickerStore {
  const fallback = memoryStore();
  const key = (name: string): string => `${namespace}:${name}`;

  return {
    get(name) {
      try {
        const raw = globalThis.localStorage?.getItem(key(name));

        return raw === null || raw === undefined ? fallback.get(name) : (JSON.parse(raw) as unknown);
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

// ---- positioning -----------------------------------------------------------------------------------------

/** A box in viewport coordinates. */
export interface Rect {
  x: number;
  y: number;
  width: number;
  height: number;
}

export type Side = 'top' | 'bottom' | 'left' | 'right';
export type Align = 'start' | 'center' | 'end';
/** Logical placements: start and end follow the reading direction. "auto" is below, flipping above when it does not fit. */
export type Placement = 'auto' | 'top' | 'bottom' | 'start' | 'end' | `${'top' | 'bottom' | 'start' | 'end'}-${'start' | 'end'}`;

export interface PositionOptions {
  placement?: Placement;
  /** Gap between the reference and the floating box, arrow included (default 8). */
  offset?: number;
  /** Space kept clear at the viewport's edges (default 8). */
  padding?: number;
  /** How close the arrow may come to the floating box's corners, so it never sits on the rounding (default 14). */
  arrowPadding?: number;
  rtl?: boolean;
}

export interface Position {
  /** Top-left corner of the floating box, in viewport coordinates. */
  x: number;
  y: number;
  /** The side it ended up on, after flipping. */
  side: Side;
  align: Align;
  /** Where the arrow's centre sits along the floating box's edge, from its start; null when it cannot point at the reference. */
  arrow: number | null;
  /** The most room the box has on its side, to cap its height (or width) so it never runs off screen. */
  available: number;
  /** The reference has scrolled out of view. */
  hidden: boolean;
}

const OPPOSITE: Record<Side, Side> = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };

/**
 * Where to put a floating box next to a reference, the way Popper and Floating UI do it, in one pure
 * function: offset, then flip to the opposite side when the preferred one is too small, then shift along the
 * cross axis to stay on screen, then size to the room left, then place the arrow at the reference's centre,
 * clamped clear of the corners, and hide when the reference has scrolled away. Pure, so it is tested without
 * a browser; Popover applies it.
 */
export function computePosition(reference: Rect, floating: { width: number; height: number }, viewport: Rect, options: PositionOptions = {}): Position {
  const offset = options.offset ?? 8;
  const padding = options.padding ?? 8;
  const arrowPadding = options.arrowPadding ?? 14;
  const rtl = options.rtl ?? false;
  const [main = 'auto', alignPart] = String(options.placement ?? 'auto').split('-') as [string, string | undefined];
  const logical = (side: string): Side => (side === 'start' ? (rtl ? 'right' : 'left') : side === 'end' ? (rtl ? 'left' : 'right') : (side as Side));
  const preferred: Side = main === 'auto' ? 'bottom' : logical(main);
  const align: Align = alignPart === 'start' || alignPart === 'end' ? alignPart : main === 'auto' ? 'start' : 'center';

  const space: Record<Side, number> = {
    top: reference.y - viewport.y - offset - padding,
    bottom: viewport.y + viewport.height - (reference.y + reference.height) - offset - padding,
    left: reference.x - viewport.x - offset - padding,
    right: viewport.x + viewport.width - (reference.x + reference.width) - offset - padding,
  };
  const need = (side: Side): number => (side === 'top' || side === 'bottom' ? floating.height : floating.width);

  // Flip: keep the preferred side if it fits; else the opposite if that fits; else whichever has more room.
  let side = preferred;

  if (space[side] < need(side)) {
    const other = OPPOSITE[side];
    side = space[other] >= need(other) || space[other] > space[side] ? other : side;
  }

  const vertical = side === 'top' || side === 'bottom';
  const size = vertical ? Math.min(floating.height, Math.max(0, space[side])) : floating.height;
  const width = vertical ? floating.width : Math.min(floating.width, Math.max(0, space[side]));

  // Main axis.
  let x = side === 'left' ? reference.x - offset - width : side === 'right' ? reference.x + reference.width + offset : 0;
  let y = side === 'top' ? reference.y - offset - size : side === 'bottom' ? reference.y + reference.height + offset : 0;

  // Cross axis: aligned, then shifted on screen. Start and end mirror under RTL for top and bottom.
  if (vertical) {
    const startEdge = rtl ? reference.x + reference.width - width : reference.x;
    const endEdge = rtl ? reference.x : reference.x + reference.width - width;
    x = align === 'start' ? startEdge : align === 'end' ? endEdge : reference.x + reference.width / 2 - width / 2;
    x = Math.min(Math.max(x, viewport.x + padding), viewport.x + viewport.width - padding - width);
  } else {
    y = align === 'start' ? reference.y : align === 'end' ? reference.y + reference.height - size : reference.y + reference.height / 2 - size / 2;
    y = Math.min(Math.max(y, viewport.y + padding), viewport.y + viewport.height - padding - size);
  }

  // The arrow points at the reference's centre, never into the floating box's rounded corners.
  const along = vertical ? reference.x + reference.width / 2 - x : reference.y + reference.height / 2 - y;
  const length = vertical ? width : size;
  const arrow = length >= arrowPadding * 2 && along >= arrowPadding && along <= length - arrowPadding ? along : length >= arrowPadding * 2 ? Math.min(Math.max(along, arrowPadding), length - arrowPadding) : null;

  const hidden =
    reference.y + reference.height < viewport.y || reference.y > viewport.y + viewport.height || reference.x + reference.width < viewport.x || reference.x > viewport.x + viewport.width;

  return { x: Math.round(x), y: Math.round(y), side, align, arrow: arrow === null ? null : Math.round(arrow), available: Math.max(0, Math.floor(space[side])), hidden };
}

/**
 * Calls `update` whenever either element could have moved: either one resizing, any scroll (captured, so a
 * scrolling container counts too), a window resize, or the visual viewport changing (pinch zoom, the
 * on-screen keyboard). Batched to one call per frame. Returns the function that stops it.
 */
export function autoUpdate(reference: Element, floating: Element, update: () => void): () => void {
  const view: Window = reference.ownerDocument.defaultView ?? (globalThis as unknown as Window);
  let frame = 0;
  const schedule = (): void => {
    if (frame === 0) {
      frame = (view.requestAnimationFrame ?? ((cb: FrameRequestCallback) => setTimeout(() => cb(0), 16) as unknown as number))(() => {
        frame = 0;
        update();
      });
    }
  };
  const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(schedule);
  const viewport = view.visualViewport ?? null;

  observer?.observe(reference);
  observer?.observe(floating);
  view.addEventListener('scroll', schedule, { capture: true, passive: true });
  view.addEventListener('resize', schedule, { passive: true });
  viewport?.addEventListener('resize', schedule);
  viewport?.addEventListener('scroll', schedule);
  update();

  return () => {
    observer?.disconnect();
    view.removeEventListener('scroll', schedule, { capture: true });
    view.removeEventListener('resize', schedule);
    viewport?.removeEventListener('resize', schedule);
    viewport?.removeEventListener('scroll', schedule);

    if (frame !== 0) {
      (view.cancelAnimationFrame ?? clearTimeout)(frame);
    }
  };
}

export interface PopoverOptions {
  placement?: Placement;
  offset?: number;
  /** Draw the arrow (caret) pointing at the trigger (default true). */
  arrow?: boolean;
  /** At or below this viewport width (CSS px) the panel is a bottom sheet instead of a popover (default 640; 0 never). */
  sheetBreakpoint?: number;
  /** Called when the user dismisses the sheet (backdrop tap, drag down). */
  onDismiss?: () => void;
}

/**
 * The popover behind both pickers: puts the panel in the top layer (the `popover` attribute, so no ancestor's
 * overflow or z-index can clip it), positions it against the trigger with computePosition() and keeps it
 * there with autoUpdate(), and points the arrow at the trigger. `data-placement` carries the side it landed
 * on, the way Bootstrap's popovers carry `data-popper-placement`, so the stylesheet turns the arrow with no
 * script.
 *
 * On a narrow viewport it is a bottom sheet instead: a backdrop, a drag handle (drag down to dismiss, up to
 * expand), the page's scroll locked, and the sheet kept above the on-screen keyboard through the visual
 * viewport. Framework-free: the vanilla picker drives it from open() and close(), React from an effect.
 */
export class Popover {
  private stop: (() => void) | null = null;
  private undoSheet: Array<() => void> = [];
  private sheet = false;

  constructor(
    private readonly root: HTMLElement,
    private readonly trigger: HTMLElement,
    private readonly panel: HTMLElement,
    private readonly arrow: HTMLElement | null,
    private readonly backdrop: HTMLElement | null,
    private readonly handle: HTMLElement | null,
    private readonly options: PopoverOptions = {},
  ) {
    // Manual: the picker decides when to close (Escape, outside click), not the browser's light dismiss.
    if (typeof (panel as HTMLElement & { showPopover?: unknown }).showPopover === 'function') {
      panel.setAttribute('popover', 'manual');
    }
  }

  /** Whether the panel is a bottom sheet (decided on each open, from the viewport's width). */
  get isSheet(): boolean {
    return this.sheet;
  }

  open(): void {
    const view = this.root.ownerDocument.defaultView;
    const breakpoint = this.options.sheetBreakpoint ?? 640;

    this.sheet = breakpoint > 0 && !!view?.matchMedia?.(`(max-width: ${breakpoint}px)`).matches;
    this.root.toggleAttribute('data-sheet', this.sheet);
    this.panel.toggleAttribute('data-sheet', this.sheet);
    this.showTopLayer();

    if (this.sheet) {
      this.openSheet();
    } else {
      this.stop = autoUpdate(this.trigger, this.panel, () => this.place());
    }
  }

  close(): void {
    this.stop?.();
    this.stop = null;

    for (const undo of this.undoSheet.splice(0)) {
      undo();
    }

    this.panel.removeAttribute('data-expanded');

    try {
      (this.panel as HTMLElement & { hidePopover?: () => void }).hidePopover?.();
    } catch {
      // Already hidden.
    }
  }

  destroy(): void {
    this.close();
  }

  /** Positions the panel now; autoUpdate() calls this on every move. */
  place(): void {
    const view = this.root.ownerDocument.defaultView;

    if (!view || this.sheet) {
      return;
    }

    const trigger = this.trigger.getBoundingClientRect();
    const viewport = view.visualViewport ?? null;
    // The panel's natural size: measured with its cap lifted, so a cap from the last position cannot stick,
    // and as laid out (offsetWidth), not as drawn: the open animation scales it, which getBoundingClientRect
    // would report, leaving it too narrow to keep on screen once the animation ends.
    this.panel.style.maxBlockSize = '';
    const own = { width: this.panel.offsetWidth, height: this.panel.offsetHeight };
    const rtl = (this.root.closest('[dir]')?.getAttribute('dir') ?? this.root.ownerDocument.documentElement.getAttribute('dir')) === 'rtl';
    const showArrow = this.options.arrow !== false;
    const result = computePosition(
      { x: trigger.left, y: trigger.top, width: trigger.width, height: trigger.height },
      { width: own.width, height: own.height },
      { x: viewport?.offsetLeft ?? 0, y: viewport?.offsetTop ?? 0, width: viewport?.width ?? view.innerWidth, height: viewport?.height ?? view.innerHeight },
      { placement: this.options.placement, offset: (this.options.offset ?? 8) + (showArrow ? 0 : -4), rtl },
    );

    this.panel.style.left = `${result.x}px`;
    this.panel.style.top = `${result.y}px`;

    if (result.side === 'top' || result.side === 'bottom') {
      this.panel.style.maxBlockSize = `${Math.max(160, result.available)}px`;
    }

    this.panel.setAttribute('data-placement', `${result.side}-${result.align}`);
    this.panel.toggleAttribute('data-reference-hidden', result.hidden);
    this.panel.style.setProperty('--_lep-origin', result.arrow === null ? 'center' : result.side === 'top' || result.side === 'bottom' ? `${result.arrow}px ${result.side === 'top' ? '100%' : '0'}` : `${result.side === 'left' ? '100%' : '0'} ${result.arrow}px`);

    if (this.arrow) {
      this.arrow.hidden = !showArrow || result.arrow === null;
      this.arrow.style.left = result.side === 'top' || result.side === 'bottom' ? `${result.arrow ?? 0}px` : '';
      this.arrow.style.top = result.side === 'left' || result.side === 'right' ? `${result.arrow ?? 0}px` : '';
    }
  }

  private showTopLayer(): void {
    const panel = this.panel as HTMLElement & { showPopover?: () => void };

    try {
      if (panel.hasAttribute('popover') && !panel.matches(':popover-open')) {
        panel.showPopover?.();
      }
    } catch {
      // No top layer (an old browser, or the element is detached): it is positioned fixed in place instead.
    }
  }

  private openSheet(): void {
    const doc = this.root.ownerDocument;
    const view = doc.defaultView;
    const body = doc.body;
    const previous = body.style.overflow;

    this.panel.style.left = '';
    this.panel.style.top = '';
    this.panel.style.maxBlockSize = '';
    this.panel.setAttribute('data-placement', 'bottom-center');

    if (this.arrow) {
      this.arrow.hidden = true;
    }

    // The page behind does not scroll while the sheet is up.
    body.style.overflow = 'hidden';
    this.undoSheet.push(() => {
      body.style.overflow = previous;
    });

    // Above the on-screen keyboard: the visual viewport shrinks when it opens, the layout viewport does not.
    const viewport = view?.visualViewport ?? null;

    if (view && viewport) {
      const lift = (): void => {
        this.panel.style.setProperty('--_lep-keyboard', `${Math.max(0, view.innerHeight - viewport.height - viewport.offsetTop)}px`);
      };

      lift();
      viewport.addEventListener('resize', lift);
      viewport.addEventListener('scroll', lift);
      this.undoSheet.push(() => {
        viewport.removeEventListener('resize', lift);
        viewport.removeEventListener('scroll', lift);
        this.panel.style.removeProperty('--_lep-keyboard');
      });
    }

    if (this.backdrop) {
      const dismiss = (): void => this.options.onDismiss?.();

      this.backdrop.hidden = false;
      this.backdrop.addEventListener('click', dismiss);
      this.undoSheet.push(() => {
        this.backdrop!.hidden = true;
        this.backdrop!.removeEventListener('click', dismiss);
      });
    }

    if (this.handle) {
      this.undoSheet.push(this.dragHandle(this.handle));
    }
  }

  /** Drag the handle down past a fifth of the sheet to dismiss it, up to expand it to full height. */
  private dragHandle(handle: HTMLElement): () => void {
    let start: number | null = null;
    let delta = 0;

    const down = (event: PointerEvent): void => {
      start = event.clientY;
      delta = 0;
      handle.setPointerCapture?.(event.pointerId);
    };
    const move = (event: PointerEvent): void => {
      if (start === null) return;
      delta = event.clientY - start;
      this.panel.style.transform = delta > 0 ? `translateY(${delta}px)` : '';
    };
    const up = (): void => {
      if (start === null) return;
      start = null;
      this.panel.style.transform = '';

      if (delta > Math.max(60, this.panel.getBoundingClientRect().height / 5)) {
        this.options.onDismiss?.();
      } else if (delta < -40) {
        this.panel.setAttribute('data-expanded', '');
      }
    };
    const toggle = (event: KeyboardEvent): void => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        this.panel.toggleAttribute('data-expanded');
      }
    };

    handle.addEventListener('pointerdown', down);
    handle.addEventListener('pointermove', move);
    handle.addEventListener('pointerup', up);
    handle.addEventListener('pointercancel', up);
    handle.addEventListener('keydown', toggle);

    return () => {
      handle.removeEventListener('pointerdown', down);
      handle.removeEventListener('pointermove', move);
      handle.removeEventListener('pointerup', up);
      handle.removeEventListener('pointercancel', up);
      handle.removeEventListener('keydown', toggle);
    };
  }

  /** Expands the sheet to full height (search focus does, so results are not hidden behind the keyboard). */
  expand(): void {
    if (this.sheet) {
      this.panel.setAttribute('data-expanded', '');
    }
  }
}

// ---- the vanilla picker ------------------------------------------------------------------------------

const el = <K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Record<string, string | boolean | null | undefined> = {}, text?: string): HTMLElementTagNameMap[K] => {
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

type ResolvedOptions = Required<Omit<PickerOptions, 'source' | 'target' | 'store'>> & Pick<PickerOptions, 'source' | 'target' | 'store'>;
type Handler<K extends keyof PickerEvents> = (detail: PickerEvents[K]) => void;

export class Picker {
  /**
   * Starts a picker on an element; chain the options, then mount(). An element that already has a live
   * picker (auto-initialised, say) returns that picker, so its handlers and methods reach the one on screen.
   */
  static create(element: HTMLElement, options: PickerOptions = {}): Picker {
    const live = (element as Mountable)[MOUNTED];

    return live && live.isAttached() ? live : new Picker(element, options);
  }

  readonly element: Mountable;
  options: ResolvedOptions;
  data: PickerPayload | null = null;
  query = '';
  active: HTMLElement | null = null;
  root?: HTMLDivElement;
  trigger?: HTMLButtonElement;
  panel!: HTMLDivElement;
  searchInput!: HTMLInputElement;
  private tabs!: HTMLDivElement;
  private tones!: HTMLDivElement;
  private body!: HTMLDivElement;
  private status!: HTMLDivElement;
  private handlers = new Map<keyof PickerEvents, Array<Handler<keyof PickerEvents>>>();
  private cleanup: Array<() => void> = [];
  private targetCleanup: Array<() => void> = [];
  private caretKnown = false;
  private byHex: Map<string, PickerEmoji> | null = null;
  private memo: PickerSection[] | null = null;
  private recentStale = false;
  private activeTab: string | null = null;
  private loads = 0;
  private abort: AbortController | null = null;
  private searchTimer: ReturnType<typeof setTimeout> | null = null;
  private popover: Popover | null = null;

  constructor(element: HTMLElement, options: PickerOptions = {}) {
    this.element = element as Mountable;
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
      searchDelay: 80,
      trigger: '🙂',
      placement: 'auto',
      offset: 8,
      arrow: true,
      sheetBreakpoint: 640,
      ...options,
    } as ResolvedOptions;
    this.options.placement = parsePlacement(this.options.placement);
    this.options.tone = clampTone(this.options.tone);
    this.options.columns = clampColumns(this.options.columns);
  }

  // Every setter takes effect on a mounted picker too: display options redraw, data options reload.
  source(source: PickerSource): this { this.options.source = source; return this.reload(); }
  locale(locale: string | null): this { this.options.locale = locale; return this.reload(); }
  skinTone(tone: number): this { this.options.tone = clampTone(tone); return this.refresh(); }
  sort(mode: SortMode): this { this.options.sort = mode; return this.refresh(); }
  categories(list: string[]): this { this.options.categories = [...list]; return this.refresh(); }
  columns(count: number): this {
    this.options.columns = clampColumns(count);
    this.root?.style.setProperty(`--${PREFIX}-picker-columns`, String(this.options.columns));

    return this.refresh();
  }
  /** Hide emoji newer than this Emoji version: 'auto' (default) asks the browser, null shows everything. */
  maxVersion(version: string | null): this { this.options.maxVersion = version; return this.reload(); }
  closeOnSelect(close = true): this { this.options.closeOnSelect = close; return this; }
  inline(inline = true): this { this.options.inline = inline; return this; }

  target(target: string | Insertable | null): this {
    this.options.target = typeof target === 'string' ? query<Insertable>(target) : target;
    this.caretKnown = false;

    if (this.root) {
      this.watchTarget();
    }

    return this;
  }

  history({ max, store, order }: { max?: number; store?: PickerStore; order?: RecentOrder } = {}): this {
    if (max !== undefined) this.options.maxRecent = max;
    if (store !== undefined) this.options.store = store;
    if (order !== undefined) this.options.recentOrder = order;

    return this.refresh();
  }

  on<K extends keyof PickerEvents>(event: K, handler: (detail: PickerEvents[K]) => void): this {
    this.handlers.set(event, [...(this.handlers.get(event) ?? []), handler as Handler<keyof PickerEvents>]);

    return this;
  }

  get strings(): PickerStrings {
    return { ...DEFAULT_STRINGS, ...this.options.strings };
  }

  get store(): PickerStore {
    this.options.store ??= localStorageStore(this.options.userKey ? `${PREFIX}:${this.options.userKey}` : PREFIX);

    return this.options.store;
  }

  /** Builds the UI and loads the payload. Resolves once the emoji are drawn. */
  async mount(): Promise<this> {
    if (this.element[MOUNTED] === this && this.isAttached()) {
      return this;
    }

    this.element[MOUNTED] = this;
    const stored = this.store.get('tone');
    this.options.tone = stored === null || stored === undefined ? this.options.tone : clampTone(stored);
    this.buildShell();
    await this.load();

    return this;
  }

  /** Whether the picker's UI is still inside its element (a morph can strip it out). */
  isAttached(): boolean {
    return this.root !== undefined && this.element.contains(this.root);
  }

  destroy(): void {
    this.popover?.destroy();
    this.popover = null;
    this.loads++;
    this.abort?.abort();
    this.abort = null;

    if (this.searchTimer !== null) {
      clearTimeout(this.searchTimer);
    }

    for (const undo of [...this.cleanup.splice(0), ...this.targetCleanup.splice(0)]) {
      undo();
    }

    this.root?.remove();
    this.trigger?.remove();
    this.root = undefined;
    this.trigger = undefined;
    this.data = null;
    this.byHex = null;
    this.memo = null;

    if (this.element[MOUNTED] === this) {
      delete this.element[MOUNTED];
    }

    this.handlers.clear();
  }

  open(): void {
    if (this.recentStale) {
      this.refreshRecents();
    }

    this.panel.hidden = false;
    this.trigger?.setAttribute('aria-expanded', 'true');
    this.popover?.open();

    // On a phone sheet, focusing search would raise the keyboard over half the emoji; focus the sheet itself.
    if (this.popover?.isSheet) {
      this.panel.focus();
    } else {
      this.searchInput.focus();
    }
  }

  /**
   * Closes the popover. Focus goes back to the trigger by default; `'target'` sends it to the field a pick
   * was inserted into, so typing carries on; `'none'` leaves it where it went (an outside click).
   */
  close(focus: 'trigger' | 'target' | 'none' = 'trigger'): void {
    if (this.options.inline || this.panel.hidden) {
      return;
    }

    this.popover?.close();
    this.panel.hidden = true;
    this.trigger?.setAttribute('aria-expanded', 'false');

    if (focus === 'target' && this.options.target) {
      this.options.target.focus();
    } else if (focus !== 'none') {
      this.trigger?.focus();
    }
  }

  focusCell(cell: HTMLElement | null | undefined): void {
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

  // ---- loading ----

  private async load(): Promise<void> {
    if (!this.root) {
      return;
    }

    // Only the newest load may draw: one that a remount, a locale change or destroy() overtook is dropped.
    const id = ++this.loads;
    this.abort?.abort();
    this.abort = typeof AbortController === 'undefined' ? null : new AbortController();
    this.status.textContent = this.strings.loading;

    try {
      const source = this.options.source ?? new StaticSource({ groups: [], custom: [] });
      const payload = await source.load(this.options.locale, this.abort?.signal);

      if (id !== this.loads) {
        return;
      }

      const cap = this.options.maxVersion === 'auto' ? await detectMaxVersionOnce() : this.options.maxVersion;

      if (id !== this.loads) {
        return;
      }

      this.data = capPayload(payload, cap);
      this.byHex = null;
      this.memo = null;
      this.status.textContent = '';
      this.render();
      this.emit('ready', { picker: this });
    } catch (error) {
      if (id !== this.loads) {
        return;
      }

      this.status.textContent = this.strings.failed;
      this.emit('error', { error });
    }
  }

  private reload(): this {
    if (this.root) {
      void this.load();
    }

    return this;
  }

  private refresh(): this {
    this.memo = null;

    if (this.root && this.data) {
      this.render();
    }

    return this;
  }

  /** Redraws Frequently used after picks made while the panel was open, now that nothing is under the pointer. */
  private refreshRecents(): void {
    this.recentStale = false;
    this.refresh();
  }

  // ---- building ----

  private listen<K extends keyof HTMLElementEventMap>(node: EventTarget, type: K, handler: (event: HTMLElementEventMap[K]) => void, bucket: Array<() => void> = this.cleanup): void {
    node.addEventListener(type, handler as EventListener);
    bucket.push(() => node.removeEventListener(type, handler as EventListener));
  }

  private emit<K extends keyof PickerEvents>(name: K, detail: PickerEvents[K]): void {
    for (const handler of this.handlers.get(name) ?? []) {
      handler(detail);
    }
  }

  /**
   * A field nobody has focused reports its caret at 0, so the first pick would land before text already in
   * it. The caret is trusted once the field has had focus; until then, picks append. Re-targeting drops the
   * previous field's listener.
   */
  private watchTarget(): void {
    for (const undo of this.targetCleanup.splice(0)) {
      undo();
    }

    const target = this.options.target;

    if (!target) {
      return;
    }

    this.listen(target, 'focus', () => {
      this.caretKnown = true;
    }, this.targetCleanup);
    this.caretKnown = document.activeElement === target;
  }

  private get rtl(): boolean {
    return (this.root?.closest('[dir]')?.getAttribute('dir') ?? document.documentElement.getAttribute('dir')) === 'rtl';
  }

  private buildShell(): void {
    const s = this.strings;
    const id = `${PREFIX}-picker-${Math.random().toString(36).slice(2, 9)}`;
    const inline = this.options.inline;

    this.root = el('div', { class: `${PREFIX}-picker` });
    this.root.style.setProperty(`--${PREFIX}-picker-columns`, String(this.options.columns));

    if (!inline) {
      this.trigger = el('button', { type: 'button', class: `${PREFIX}-picker-trigger`, 'aria-haspopup': 'dialog', 'aria-expanded': 'false', 'aria-controls': id, 'aria-label': s.open }, this.options.trigger || '🙂');
      this.listen(this.trigger, 'click', () => (this.panel.hidden ? this.open() : this.close()));
      this.root.append(this.trigger);
    }

    // Inline, the picker is part of the page, not a dialog.
    this.panel = el('div', { class: `${PREFIX}-picker-panel`, id, role: inline ? 'group' : 'dialog', 'aria-label': s.open, hidden: !inline });
    this.searchInput = el('input', { type: 'search', class: `${PREFIX}-picker-search`, placeholder: s.search, 'aria-label': s.search, autocomplete: 'off', spellcheck: 'false' });
    this.tabs = el('div', { class: `${PREFIX}-picker-tabs`, role: 'tablist', 'aria-label': s.open });
    this.tones = el('div', { class: `${PREFIX}-picker-tones`, role: 'radiogroup', 'aria-label': s.tone });
    this.body = el('div', { class: `${PREFIX}-picker-body` });
    this.status = el('div', { class: `${PREFIX}-picker-status`, role: 'status', 'aria-live': 'polite' }, s.loading);

    this.panel.append(this.searchInput, this.tabs, this.body, this.tones, this.status);

    if (!inline && this.trigger) {
      // The caret, a backdrop and a drag handle for the phone sheet: decoration, hidden from assistive tech.
      const arrow = el('div', { class: `${PREFIX}-picker-arrow`, 'aria-hidden': 'true', hidden: !this.options.arrow });
      const handle = el('div', { class: `${PREFIX}-picker-handle`, 'aria-hidden': 'true' });
      const backdrop = el('div', { class: `${PREFIX}-picker-backdrop`, 'aria-hidden': 'true', hidden: true });

      this.panel.tabIndex = -1;
      this.panel.prepend(arrow, handle);
      this.root.append(backdrop);
      this.popover = new Popover(this.root, this.trigger, this.panel, arrow, backdrop, handle, {
        placement: this.options.placement,
        offset: this.options.offset,
        arrow: this.options.arrow,
        sheetBreakpoint: this.options.sheetBreakpoint,
        onDismiss: () => this.close(),
      });
      this.listen(this.searchInput, 'focus', () => this.popover?.expand());
      this.swipeSections();
    }

    this.root.append(this.panel);
    this.element.replaceChildren(this.root);
    this.watchTarget();

    this.listen(this.searchInput, 'input', () => this.scheduleSearch());
    this.listen(this.panel, 'keydown', (event) => this.onKey(event));
    // One listener per container, not per button, so redrawing never stacks listeners.
    this.listen(this.body, 'click', (event) => {
      const cell = (event.target as Element | null)?.closest?.<HTMLElement>(`[${ATTR}-hexcode], [${ATTR}-custom]`);

      if (cell) {
        this.select(cell);
      }
    });
    this.listen(this.tabs, 'click', (event) => {
      const tab = (event.target as Element | null)?.closest?.<HTMLElement>('[role="tab"]');

      if (tab) {
        this.showSection(tab.getAttribute(`${ATTR}-section`) ?? '');
      }
    });
    this.listen(this.tones, 'click', (event) => {
      const radio = (event.target as Element | null)?.closest?.<HTMLElement>('[role="radio"]');

      if (radio) {
        this.setTone(Number(radio.getAttribute(`${ATTR}-tone`)));
      }
    });

    // A popover closes when the user clicks or tabs away from it, leaving focus where they put it.
    this.listen(document, 'pointerdown', (event) => {
      if (!this.panel.hidden && this.root && !this.root.contains(event.target as Node)) {
        this.close('none');
      }
    });
    this.listen(this.root, 'focusout', (event) => {
      const next = event.relatedTarget as Node | null;

      if (next !== null && this.root && !this.root.contains(next)) {
        this.close('none');

        if (inline && this.recentStale) {
          this.refreshRecents();
        }
      }
    });

    if (inline) {
      this.listen(this.root, 'pointerleave', () => {
        if (this.recentStale && !this.root?.contains(document.activeElement)) {
          this.refreshRecents();
        }
      });
    }
  }

  /** On the phone sheet, a horizontal swipe across the emoji moves to the next or previous category. */
  private swipeSections(): void {
    let start: { x: number; y: number } | null = null;

    this.listen(this.body, 'pointerdown', (event) => {
      start = event.pointerType === 'touch' && this.popover?.isSheet ? { x: event.clientX, y: event.clientY } : null;
    });
    this.listen(this.body, 'pointerup', (event) => {
      if (!start) return;

      const dx = event.clientX - start.x;
      const dy = event.clientY - start.y;
      start = null;

      if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) return;

      const tabs = [...this.tabs.querySelectorAll<HTMLElement>('[role="tab"]')];
      const current = tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true');
      const next = tabs[current + ((dx < 0) !== this.rtl ? 1 : -1)];

      if (next) {
        this.showSection(next.getAttribute(`${ATTR}-section`) ?? '');
      }
    });
  }

  private scheduleSearch(): void {
    if (this.searchTimer !== null) {
      clearTimeout(this.searchTimer);
      this.searchTimer = null;
    }

    const run = (): void => {
      this.searchTimer = null;
      this.query = this.searchInput.value;
      this.renderBody();
    };
    const delay = Math.max(0, Number(this.options.searchDelay) || 0);

    if (delay === 0) {
      run();
    } else {
      this.searchTimer = setTimeout(run, delay);
    }
  }

  private render(): void {
    this.renderTabs();
    this.renderTones();
    this.renderBody();
  }

  private sections(): PickerSection[] {
    this.memo ??= buildSections(this.data ?? { groups: [] }, {
      categories: this.options.categories,
      sort: this.options.sort,
      recent: readRecent(this.store.get('recent')),
      recentOrder: this.options.recentOrder,
      strings: this.options.strings,
    });

    return this.memo;
  }

  private index(): Map<string, PickerEmoji> {
    this.byHex ??= indexPayload(this.data ?? { groups: [] });

    return this.byHex;
  }

  private sectionId(slug: string): string {
    return `${this.panel.id}-section-${slug}`;
  }

  private renderTabs(): void {
    // A section the cap or the policy emptied gets no tab: it would scroll to nothing.
    const sections = this.sections().filter((section) => section.items.length > 0);
    const active = sections.some((section) => section.slug === this.activeTab) ? this.activeTab : (sections[0]?.slug ?? null);

    this.activeTab = active;
    this.tabs.replaceChildren(
      ...sections.map((section) => {
        const first = section.custom ? null : section.items[0];
        const selected = section.slug === active;

        return el('button', {
          type: 'button',
          role: 'tab',
          class: `${PREFIX}-picker-tab`,
          title: section.label,
          'aria-label': section.label,
          'aria-selected': String(selected),
          'aria-controls': this.sectionId(section.slug),
          tabindex: selected ? '0' : '-1',
          [`${ATTR}-section`]: section.slug,
        }, first ? charOf(first.pick ?? first.hexcode) : '★');
      }),
    );
  }

  /** Clears any search, marks the tab, and scrolls the body — never the page — to a section. */
  private showSection(slug: string): void {
    if (this.query !== '' || this.searchInput.value !== '') {
      this.query = '';
      this.searchInput.value = '';
      this.renderBody();
    }

    this.activeTab = slug;

    for (const tab of this.tabs.querySelectorAll<HTMLElement>('[role="tab"]')) {
      const selected = tab.getAttribute(`${ATTR}-section`) === slug;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;

      // A tab bar that scrolls sideways (phones) keeps the selected tab in view, without scrolling the page.
      if (selected && this.tabs.scrollWidth > this.tabs.clientWidth) {
        this.tabs.scrollLeft = tab.offsetLeft - this.tabs.offsetLeft - (this.tabs.clientWidth - tab.offsetWidth) / 2;
      }
    }

    const section = this.body.querySelector<HTMLElement>(`section[${ATTR}-section="${slug}"]`);

    if (section) {
      this.body.scrollTop = section.offsetTop - this.body.offsetTop;
    }
  }

  private renderTones(): void {
    const s = this.strings;

    this.tones.replaceChildren(
      ...TONE_SWATCHES.map((hand, tone) =>
        el('button', {
          type: 'button',
          role: 'radio',
          class: `${PREFIX}-picker-tone`,
          'aria-checked': String(tone === this.options.tone),
          'aria-label': s.tones[tone] ?? DEFAULT_STRINGS.tones[tone],
          tabindex: tone === this.options.tone ? '0' : '-1',
          [`${ATTR}-tone`]: String(tone),
        }, hand),
      ),
    );
  }

  /** Applies a tone, updating the radios in place so the one the user is on keeps focus. */
  private setTone(tone: number): void {
    this.options.tone = clampTone(tone);
    this.store.set('tone', this.options.tone);

    for (const radio of this.tones.querySelectorAll<HTMLElement>('[role="radio"]')) {
      const checked = Number(radio.getAttribute(`${ATTR}-tone`)) === this.options.tone;
      radio.setAttribute('aria-checked', String(checked));
      radio.tabIndex = checked ? 0 : -1;
    }

    this.renderBody();
  }

  private renderBody(): void {
    const s = this.strings;
    const term = this.query.trim();
    const sections: PickerSection[] = term ? searchResults(this.sections(), term, s) : this.sections();
    const nodes: HTMLElement[] = [];
    let count = 0;

    for (const section of sections) {
      if (section.items.length === 0) {
        continue;
      }

      const heading = el('div', { class: `${PREFIX}-picker-heading`, id: `${this.panel.id}-${section.slug}` }, section.label);
      const grid = el('div', { class: `${PREFIX}-picker-grid`, role: 'grid', 'aria-labelledby': heading.id });
      let row: HTMLDivElement | null = null;

      section.items.forEach((item, index) => {
        if (index % this.options.columns === 0 || row === null) {
          row = el('div', { role: 'row', class: `${PREFIX}-picker-row` });
          grid.append(row);
        }

        row.append(section.custom ? this.customCell(item as PickerCustom) : this.cell(item as PickerEmoji));
        count++;
      });

      const block = el('section', { class: `${PREFIX}-picker-section`, id: this.sectionId(section.slug), [`${ATTR}-section`]: section.slug });

      block.append(heading, grid);
      nodes.push(block);
    }

    this.body.replaceChildren(...nodes);
    this.status.textContent = term ? resultText(s, count) : '';

    const first = this.body.querySelector<HTMLElement>('[role="gridcell"]');

    this.active = null;

    if (first) {
      first.tabIndex = 0;
      this.active = first;
    }
  }

  private cell(item: PickerEmoji): HTMLButtonElement {
    const hexcode = item.pick ?? withTone(item, this.options.tone);

    return el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell`, title: item.name, 'aria-label': item.name, [`${ATTR}-hexcode`]: hexcode, [`${ATTR}-base`]: item.hexcode }, charOf(hexcode));
  }

  private customCell(item: PickerCustom): HTMLButtonElement {
    const button = el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell`, title: item.label, 'aria-label': item.label, [`${ATTR}-custom`]: item.name });
    const image = el('img', { src: item.image, alt: '', class: `${PREFIX} ${PREFIX}-image`, draggable: 'false', loading: 'lazy' });

    button.append(image);

    return button;
  }

  // ---- interaction ----

  private onKey(event: KeyboardEvent): void {
    const target = event.target as Element | null;

    if (event.key === 'Escape') {
      // Only a popover that actually closes takes the key; otherwise it reaches the page (a <dialog> around
      // an inline picker still closes on Escape).
      if (!this.options.inline && !this.panel.hidden) {
        event.preventDefault();
        event.stopPropagation();
        this.close();
      }

      return;
    }

    const tab = target?.closest?.<HTMLElement>('[role="tab"]');
    const radio = target?.closest?.<HTMLElement>('[role="radio"]');

    if (tab || radio) {
      const items = [...(tab ? this.tabs : this.tones).querySelectorAll<HTMLElement>(tab ? '[role="tab"]' : '[role="radio"]')];
      const next = rovingIndex(items.length, items.indexOf((tab ?? radio) as HTMLElement), event.key, this.rtl);

      if (next !== null) {
        event.preventDefault();
        const item = items[next];
        item?.focus();

        if (tab && item) {
          this.showSection(item.getAttribute(`${ATTR}-section`) ?? '');
        } else if (item) {
          this.setTone(Number(item.getAttribute(`${ATTR}-tone`)));
        }
      }

      return;
    }

    const cell = target?.closest?.<HTMLElement>('[role="gridcell"]');

    if (!cell) {
      if (event.key === 'ArrowDown' && target === this.searchInput) {
        event.preventDefault();
        this.focusCell(this.body.querySelector<HTMLElement>('[role="gridcell"]'));
      }

      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      this.select(cell);

      return;
    }

    const next = gridTarget(this.body, cell, event.key, this.rtl);

    if (next === null) {
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'PageUp', 'PageDown', 'Home', 'End'].includes(event.key)) {
        event.preventDefault();
      }

      return;
    }

    event.preventDefault();

    if (next === 'search') {
      this.searchInput.focus();
    } else {
      this.focusCell(next);
    }
  }

  private select(cell: HTMLElement): void {
    const name = cell.getAttribute(`${ATTR}-custom`);
    let detail: SelectDetail;

    if (name !== null) {
      const custom = (this.data?.custom ?? []).find((c) => c.name === name);
      detail = { emoji: customCode(name, this.data), hexcode: null, name: custom?.label ?? name, shortcode: name, custom: true };
    } else {
      const hexcode = cell.getAttribute(`${ATTR}-hexcode`) ?? '';
      const base = cell.getAttribute(`${ATTR}-base`) ?? hexcode;
      const item = this.index().get(base);

      detail = { emoji: charOf(hexcode), hexcode, name: item?.name ?? '', shortcode: item?.shortcode ?? null, custom: false };
      this.store.set('recent', recordRecent(this.store.get('recent'), base, hexcode, this.options.maxRecent));
      // Redrawn once nothing is under the pointer, so a quick second click never lands on a moved cell.
      this.recentStale = true;
    }

    const target = this.options.target;

    if (target) {
      insertText(target, detail.emoji, this.caretKnown);
    }

    this.element.dispatchEvent(new CustomEvent(EVENT, { detail, bubbles: true }));
    this.emit('select', detail);

    if (this.options.closeOnSelect && !this.options.inline) {
      this.close(target ? 'target' : 'trigger');
    }
  }
}

// ---- auto-init -------------------------------------------------------------------------------------------

/** querySelector, or null for a selector the browser rejects instead of a thrown SyntaxError. */
function query<T extends Element>(selector: string): T | null {
  try {
    return document.querySelector<T>(selector);
  } catch {
    return null;
  }
}

/**
 * Mounts a picker from its data-laranail-emoji-* attributes. Idempotent: a mounted element is left alone,
 * unless something (a Livewire or Turbo morph) stripped the picker out of it, when it is mounted afresh.
 */
export function mountElement(element: HTMLElement): Picker {
  const mounted = (element as Mountable)[MOUNTED];

  if (mounted && mounted.isAttached()) {
    return mounted;
  }

  mounted?.destroy();

  const options = parseOptions(element);
  const source = options.payload ? new StaticSource(options.payload) : options.source ? new ApiSource(options.source) : undefined;
  const { target, payload: _payload, source: _source, ...rest } = options;
  const picker = Picker.create(element, { ...rest, maxVersion: options.maxVersion === '' ? null : options.maxVersion, source });

  if (target) {
    picker.target(target);
  }

  void picker.mount();

  return picker;
}

/**
 * Mounts every [data-laranail-emoji-picker] under root now and as they are added later, with one
 * MutationObserver. Returns a function that stops observing.
 */
export function autoInit(root: Document | Element = document): () => void {
  const selector = `[${ATTR}-picker]`;
  // One picker that fails to mount (a bad option, a missing data block) must not stop the rest.
  const mount = (element: HTMLElement): void => {
    try {
      mountElement(element);
    } catch (error) {
      console.error('[laranail/emojis] picker failed to mount', element, error);
    }
  };
  const scan = (node: Node): void => {
    if (!(node instanceof Element)) return;
    if (node.matches(selector)) mount(node as HTMLElement);
    node.querySelectorAll<HTMLElement>(selector).forEach(mount);
  };
  const start = 'documentElement' in root ? root.documentElement : root;

  scan(start);

  if (typeof MutationObserver === 'undefined') {
    return () => {};
  }

  // A morph that empties a mounted picker adds no node, so a picker that lost its children is rescanned too.
  const observer = new MutationObserver((records) => records.forEach((r) => {
    r.addedNodes.forEach(scan);

    if (r.removedNodes.length > 0 && r.target instanceof Element && r.target.matches(selector)) {
      scan(r.target);
    }
  }));
  observer.observe(start, { childList: true, subtree: true });

  return () => observer.disconnect();
}

// Importing the module mounts every picker on the page. Set globalThis.__laranailEmojiNoAutoInit = true
// before importing to mount by hand instead.
const autoInitEnabled = typeof __LARANAIL_EMOJI_AUTO_INIT__ === 'undefined' || __LARANAIL_EMOJI_AUTO_INIT__;

if (autoInitEnabled && typeof document !== 'undefined' && !(globalThis as { __laranailEmojiNoAutoInit?: boolean }).__laranailEmojiNoAutoInit) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => autoInit(), { once: true });
  } else {
    autoInit();
  }
}
