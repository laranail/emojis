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
  /** Text emoticons that mean this emoji (":)" for 🙂), which search finds. */
  emoticons?: string[];
  /** The English keywords, sent beside a non-English locale's when picker.features.english_keywords is on. */
  keywords_en?: string[];
  /** Set by capPayload when the device cannot draw the emoji itself: it is drawn as an image instead. */
  draw?: 'image';
  /** Set by capPayload: the tone keys whose forms the device cannot draw, drawn as images instead. */
  imageSkins?: string[];
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

/**
 * How to draw emoji from one image set, as PayloadBuilder describes it: a rule-based set (twemoji, noto,
 * openmoji, joypixels) as a base URL, a rule, a suffix and the hexcodes it lacks; any other as a path per
 * hexcode below a base URL, or a full URL per hexcode.
 */
export interface PickerImageSet {
  set: string;
  licence: string;
  base?: string;
  rule?: 'twemoji' | 'noto' | 'openmoji' | 'joypixels';
  suffix?: string;
  missing?: string[];
  paths?: Record<string, string>;
  urls?: Record<string, string>;
}

/** How emoji are drawn: the device's own with images for what it cannot draw, only its own, or only images. */
export type RenderMode = 'auto' | 'native' | 'image';

/** A kaomoji or a special character: inserted as text, named for screen readers. */
export interface PickerText {
  text: string;
  name: string;
}

/** A group of kaomoji or symbols, as the payload carries them when those tabs are on. */
export interface PickerTextGroup {
  slug: string;
  label: string;
  items: Array<{ text?: string; char?: string; name: string }>;
}

/** What a picker shows: emoji (with custom ones), kaomoji, or special characters. */
export type PickerKind = 'emoji' | 'kaomoji' | 'symbols';

/** Parts of the picker that can be switched off; every one is on unless set to false. */
export interface PickerFeatures {
  search: boolean;
  recents: boolean;
  skinTones: boolean;
  /** A tone for each person in 🤝 and couples, and a one-off tone for any emoji, from a long press or right click. */
  perPersonTones: boolean;
  /** Let the user choose native emoji or one of the payload's image sets. Off unless switched on. */
  setSwitcher: boolean;
  /** Suggest emoji as a shortcode is typed in the field (":smi"). Off unless switched on. */
  autocomplete: boolean;
  /** A gear button with the theme, clearing recents and the keyboard shortcuts. */
  settings: boolean;
  preview: boolean;
  categoryTabs: boolean;
  custom: boolean;
}

/** The payload GET /picker serves and PayloadBuilder builds. */
export interface PickerPayload {
  dataset?: string;
  locale?: string;
  groups: PickerGroup[];
  custom?: PickerCustom[];
  /** Present when the Kaomoji tab is on. */
  kaomoji?: PickerTextGroup[];
  /** Present when the Symbols tab is on. */
  symbols?: PickerTextGroup[];
  /** The image set to fall back to (or draw everything with); absent when the picker draws native emoji only. */
  images?: PickerImageSet;
  /** Every set the user may switch between, the default first; present when the set switcher is on. */
  imageSets?: PickerImageSet[];
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
  /** What was picked from: emoji (the default), kaomoji or symbols. */
  kind?: PickerKind;
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
  /** The content tabs, shown when the payload carries kaomoji or symbols. */
  emoji?: string;
  kaomoji?: string;
  symbols?: string;
  /** The set switcher's label, and its option for the device's own emoji. */
  style?: string;
  native?: string;
  /** The settings menu: its button, the theme choice, clearing recents, and the shortcut list. */
  settings?: string;
  theme?: string;
  themeAuto?: string;
  themeLight?: string;
  themeDark?: string;
  clearRecents?: string;
  kbdShortcuts?: string;
  kbdOpen?: string;
  kbdSearch?: string;
  kbdCategory?: string;
  kbdMove?: string;
  kbdTone?: string;
  kbdClose?: string;
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
  /** Parts to switch off: { search: false, preview: false, … }. Everything is on by default. */
  features?: Partial<PickerFeatures>;
  /** 'auto' (default): the device's emoji, images for what it cannot draw; 'native': its own only; 'image': all images. */
  render?: RenderMode;
  /** 'auto' (default) follows the OS or the page's theme; 'light' or 'dark' fixes it. */
  theme?: PickerTheme;
  /** The key combination that opens the picker from its field ("Mod+Shift+." by default); null or '' for none. */
  shortcut?: string | null;
}

/** The picker's colour scheme: the OS's or the page's, or fixed. */
export type PickerTheme = 'auto' | 'light' | 'dark';

export interface PickerEvents {
  select: SelectDetail;
  ready: { picker: Picker };
  error: { error: unknown };
}

/** One rendered block: Frequently used, a Unicode group, the custom emoji, or a group of kaomoji or symbols. */
export type PickerSection =
  | { slug: string; label: string; custom?: false; text?: false; items: PickerEmoji[] }
  | { slug: string; label: string; custom: true; text?: false; items: PickerCustom[] }
  | { slug: string; label: string; custom?: false; text: true; items: PickerText[] };

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
  features: PickerFeatures;
  render: RenderMode;
  theme: PickerTheme;
  shortcut: string | null;
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
  emoji: 'Emoji',
  kaomoji: 'Kaomoji',
  symbols: 'Symbols',
  style: 'Emoji style',
  native: 'Native',
  settings: 'Settings',
  theme: 'Theme',
  themeAuto: 'Auto',
  themeLight: 'Light',
  themeDark: 'Dark',
  clearRecents: 'Clear frequently used',
  kbdShortcuts: 'Keyboard shortcuts',
  kbdOpen: 'Open the picker from the field',
  kbdSearch: 'Search',
  kbdCategory: 'Jump to a category',
  kbdMove: 'Move between emoji',
  kbdTone: 'Skin tone for one emoji',
  kbdClose: 'Close',
};

/** Every feature on. */
export const DEFAULT_FEATURES: PickerFeatures = { search: true, recents: true, skinTones: true, perPersonTones: true, setSwitcher: false, autocomplete: false, settings: true, preview: true, categoryTabs: true, custom: true };

/** Features from anywhere (an attribute's JSON, a prop): only booleans count, everything else stays on. */
export function readFeatures(value: unknown): PickerFeatures {
  const given = typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : {};

  return Object.fromEntries(Object.entries(DEFAULT_FEATURES).map(([name, on]) => [name, typeof given[name] === 'boolean' ? given[name] : on])) as unknown as PickerFeatures;
}

/**
 * Outline icons for the category tabs, as SVG path data on a 24×24 grid, drawn with currentColor so they
 * follow the theme. Paths, not markup: both pickers build the <svg> themselves, so nothing is parsed.
 */
export const TAB_ICONS: Readonly<Record<string, readonly string[]>> = {
  recent: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7v5l3 2'],
  smileys_and_emotion: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M8.5 14.5a4.5 4.5 0 0 0 7 0', 'M9 9.5h.01', 'M15 9.5h.01'],
  people_and_body: ['M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
  animals_and_nature: ['M5 19c0-8 6-14 15-15-1 9-7 15-15 15Z', 'M5 19 13 11'],
  food_and_drink: ['M4 9h13v5a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6V9Z', 'M17 11h1.5a2.5 2.5 0 0 1 0 5H17', 'M8 3v3', 'M12 3v3'],
  travel_and_places: ['M5 17h14v-5l-2-5H7l-2 5v5Z', 'M5 12h14', 'M7.5 17v2', 'M16.5 17v2', 'M8 14.5h.01', 'M16 14.5h.01'],
  activities: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M3.5 9.5C7 11 9 15 9.5 20.5', 'M20.5 14.5C17 13 15 9 14.5 3.5'],
  objects: ['M9 18h6', 'M10 21h4', 'M12 3a6 6 0 0 0-3.5 10.9V15h7v-1.1A6 6 0 0 0 12 3Z'],
  symbols: ['M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z'],
  flags: ['M5 21V4', 'M5 4h11l-2 4 2 4H5'],
  custom: ['M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z'],
};

/** The groups of one kind as sections. Kaomoji and symbols are text, inserted as they are. */
export function textSections(data: PickerPayload, kind: Exclude<PickerKind, 'emoji'>): Array<{ slug: string; label: string; text: true; items: PickerText[] }> {
  return (data[kind] ?? []).map((group) => ({
    slug: `${kind}-${group.slug}`,
    label: group.label,
    text: true as const,
    items: group.items.map((item) => ({ text: item.text ?? item.char ?? '', name: item.name })).filter((item) => item.text !== ''),
  }));
}

/** The kinds a payload can show, in tab order: emoji always, then kaomoji and symbols when it carries them. */
export function kindsOf(data: PickerPayload | null): PickerKind[] {
  return ['emoji', ...((['kaomoji', 'symbols'] as const).filter((kind) => (data?.[kind] ?? []).length > 0))];
}

/** Text items whose name or text matches every word of a term. */
export function searchText(items: PickerText[], term: string, limit: number = Infinity): PickerText[] {
  const words = fold(term).split(/\s+/).filter(Boolean);

  return words.length === 0 ? [] : items.filter((item) => words.every((word) => `${fold(item.name)} ${item.text.toLowerCase()}`.includes(word))).slice(0, limit);
}

/**
 * Marks the section scrolled to as the current tab: the topmost section still showing in the top third of
 * the scrolling body. Returns the function that stops watching. Without IntersectionObserver it does nothing.
 */
export function spySections(body: HTMLElement, onActive: (slug: string) => void): () => void {
  if (typeof IntersectionObserver === 'undefined') {
    return () => {};
  }

  const visible = new Set<Element>();
  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) visible.add(entry.target);
        else visible.delete(entry.target);
      }

      const top = [...body.querySelectorAll('section[data-laranail-emoji-section]')].find((section) => visible.has(section));
      const slug = top?.getAttribute('data-laranail-emoji-section');

      if (slug) onActive(slug);
    },
    { root: body, rootMargin: '0px 0px -66% 0px' },
  );

  body.querySelectorAll('section[data-laranail-emoji-section]').forEach((section) => observer.observe(section));

  return () => observer.disconnect();
}

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

const codepoints = (hexcode: string): number[] => hexcode.split('-').map((part) => parseInt(part, 16));
const hex = (cp: number, pad = 0, upper = false): string => {
  const text = cp.toString(16).padStart(pad, '0');

  return upper ? text.toUpperCase() : text;
};

/**
 * The filename rules of the rule-based sets, mirroring the server's Filenames class: Twemoji drops FE0F
 * unless the sequence has a ZWJ, Noto always drops it and pads, OpenMoji keeps the hexcode (dropping a lone
 * trailing FE0F), JoyPixels drops every FE0F. Pure, so the same emoji gets the same URL on both sides.
 */
export const IMAGE_RULES: Readonly<Record<NonNullable<PickerImageSet['rule']>, (hexcode: string) => string>> = {
  twemoji: (hexcode) => {
    const cps = codepoints(hexcode);

    return (cps.includes(0x200d) ? cps : cps.filter((cp) => cp !== 0xfe0f)).map((cp) => hex(cp)).join('-');
  },
  noto: (hexcode) => `emoji_u${codepoints(hexcode).filter((cp) => cp !== 0xfe0f).map((cp) => hex(cp, 4)).join('_')}`,
  openmoji: (hexcode) => {
    const cps = codepoints(hexcode);

    return cps.length === 2 && cps[1] === 0xfe0f ? hex(cps[0] ?? 0, 4, true) : hexcode;
  },
  joypixels: (hexcode) => codepoints(hexcode).filter((cp) => cp !== 0xfe0f).map((cp) => hex(cp)).join('-'),
};

/** The URL of an emoji's image in a set, or null when the set has none for it. */
export function imageUrl(set: PickerImageSet | null | undefined, hexcode: string): string | null {
  if (!set) {
    return null;
  }

  if (set.urls) {
    return set.urls[hexcode] ?? null;
  }

  if (set.paths) {
    const path = set.paths[hexcode];

    return path && set.base ? `${set.base}/${path}` : null;
  }

  const rule = set.rule ? IMAGE_RULES[set.rule] : undefined;

  if (!rule || !set.base || (set.missing ?? []).includes(hexcode)) {
    return null;
  }

  return `${set.base}/${rule(hexcode)}${set.suffix ?? ''}`;
}

/**
 * Whether a cell draws an image rather than the device's glyph: always in 'image' mode, never in 'native',
 * and in 'auto' only for what capPayload marked as beyond the device (a newer emoji, a newer toned form, a
 * flag where the OS draws letters). Only when the set has the image; otherwise the glyph is kept.
 */
export function drawsImage(item: PickerEmoji, hexcode: string, mode: RenderMode, set: PickerImageSet | null | undefined): string | null {
  if (mode === 'native' || !set) {
    return null;
  }

  const beyond = hexcode === item.hexcode ? item.draw === 'image' : Object.entries(item.skins ?? {}).some(([key, value]) => value === hexcode && (item.imageSkins ?? []).includes(key));

  return mode === 'image' || beyond ? imageUrl(set, hexcode) : null;
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

/**
 * Whether this browser draws flags (🇺🇸) as flags. Windows draws regional indicators as letters, in black, so
 * a flag comes out in colour only where the OS has flag glyphs. Null when it cannot tell.
 */
export function detectFlags(doc: Pick<Document, 'createElement'> | undefined = globalThis.document): boolean | null {
  const canvas = doc?.createElement?.('canvas') as HTMLCanvasElement | undefined;
  const context = canvas?.getContext?.('2d', { willReadFrequently: true }) as CanvasRenderingContext2D | null | undefined;

  if (!canvas || !context || typeof context.getImageData !== 'function') {
    return null;
  }

  canvas.width = canvas.height = 48;
  context.font = `24px 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji','Twemoji Mozilla',sans-serif`;
  context.textBaseline = 'top';
  context.fillStyle = '#000';

  const colour = (text: string): boolean => {
    context.clearRect(0, 0, 48, 48);
    context.fillText(text, 0, 0);
    const data = context.getImageData(0, 0, 48, 48).data;

    for (let i = 0; i + 3 < data.length; i += 4) {
      const [r = 0, g = 0, b = 0, a = 0] = [data[i], data[i + 1], data[i + 2], data[i + 3]];

      if (a > 0 && Math.max(Math.abs(r - g), Math.abs(g - b), Math.abs(r - b)) > 48) return true;
    }

    return false;
  };

  if (colour('')) {
    return null;
  }

  return colour('\u{1F1FA}\u{1F1F8}') && context.measureText('\u{1F1FA}\u{1F1F8}').width < context.measureText('\u{1F600}').width * 1.5;
}

let flagsDetected: Promise<boolean | null> | null = null;

/** detectFlags() once per page, after web fonts have loaded. */
export function detectFlagsOnce(): Promise<boolean | null> {
  flagsDetected ??= (async () => {
    try {
      await (globalThis.document as Document | undefined)?.fonts?.ready;
    } catch {
      // Measure now.
    }

    return detectFlags();
  })();

  return flagsDetected;
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
 *
 * With `fallback` (the payload's image set, in 'auto' or 'image' mode) nothing the set can draw is dropped:
 * an emoji or toned form beyond the cap is kept and marked to be drawn as an image instead, so the whole
 * catalogue stays reachable on an older device. `flags: false` (the OS has no flag glyphs, as on Windows)
 * marks every flag the same way.
 */
export function capPayload(
  data: PickerPayload,
  cap: string | null | undefined,
  detect: () => string | null = detectMaxVersion,
  fallback: { set?: PickerImageSet | null; flags?: boolean | null } = {},
): PickerPayload {
  const version = cap === 'auto' ? detect() : cap;
  const set = fallback.set ?? null;
  const noFlags = fallback.flags === false && set !== null;

  if (!version && !noFlags) {
    return data;
  }

  const fits = (v: string): boolean => !version || byVersion(v, version) <= 0;
  const versionOf = (item: PickerEmoji, key: string): string => {
    const versions = item.skin_versions;

    return !versions ? item.version : typeof versions === 'string' ? versions : (versions[key] ?? item.version);
  };
  const adjust = (item: PickerEmoji, group: string): PickerEmoji | null => {
    const flag = noFlags && group === 'flags';
    const drawn = fits(item.version) && !flag;
    const baseImage = !drawn && imageUrl(set, item.hexcode) !== null;
    const skins: Record<string, string> = {};
    const imageSkins: string[] = [];

    for (const [key, hexcode] of Object.entries(item.skins ?? {})) {
      if (fits(versionOf(item, key))) {
        skins[key] = hexcode;
      } else if (imageUrl(set, hexcode) !== null) {
        skins[key] = hexcode;
        imageSkins.push(key);
      }
    }

    if (!drawn && !baseImage && (item.base !== false || Object.keys(skins).length === 0)) {
      return item.base === false && Object.keys(skins).length > 0 ? { ...item, skins } : null;
    }

    const out: PickerEmoji = { ...item, skins };

    if (!drawn) out.draw = 'image';
    if (imageSkins.length > 0) out.imageSkins = imageSkins;

    return out.base === false && Object.keys(skins).length === 0 ? null : out;
  };

  return {
    ...data,
    groups: (data.groups ?? []).map((group) => ({
      ...group,
      emoji: group.emoji.map((item) => adjust(item, group.slug)).filter((item): item is PickerEmoji => item !== null),
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

  // An emoticon finds the emoji it stands for: ":)" is 🙂, "<3" is ❤️.
  const typed = term.trim();
  const byEmoticon = typed !== '' && !/^:?[\p{L}\p{N}_+-]+:?$/u.test(typed) ? items.filter((item) => (item.emoticons ?? []).includes(typed)) : [];

  if (byEmoticon.length > 0) {
    return byEmoticon.slice(0, limit);
  }

  // ":smile", ":smile:" and "smile" are the same search.
  const words = fold(term).split(/\s+/).map((word) => word.replace(/^:+|:+$/g, '')).filter(Boolean);

  if (words.length === 0) {
    return [];
  }

  const phrase = words.join(' ');
  const scored: Array<[number, number, PickerEmoji]> = [];

  for (const item of items) {
    const { name, code, keywords, haystack } = folded(item);

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

/**
 * An emoji's searchable text, folded once and kept for as long as the emoji object lives: folding (Unicode
 * NFD and diacritic stripping) every name and keyword of ~1,900 emoji on every keystroke was the bulk of a
 * search's cost.
 */
const foldedCache = new WeakMap<PickerEmoji, { name: string; code: string; keywords: string[]; haystack: string }>();

function folded(item: PickerEmoji): { name: string; code: string; keywords: string[]; haystack: string } {
  let entry = foldedCache.get(item);

  if (!entry) {
    const name = fold(item.name);
    const code = fold(item.shortcode ?? '');
    const keywords = (item.keywords ?? []).map(fold);
    const english = (item.keywords_en ?? []).map(fold);
    entry = { name, code, keywords, haystack: `${name} ${code} ${keywords.join(' ')} ${english.join(' ')}` };
    foldedCache.set(item, entry);
  }

  return entry;
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
  return search(sections.filter((s): s is Extract<PickerSection, { items: PickerEmoji[] }> => !s.custom && !s.text && s.slug !== 'recent').flatMap((s) => s.items as PickerEmoji[]), term, limit);
}

/** The most results a search draws; a one-letter term would otherwise draw nearly every emoji. */
export const MAX_RESULTS = 200;

/**
 * What a search shows: the ranked emoji, then the matching custom emoji, as sections. Shared by both
 * pickers so a term finds the same things in each.
 */
export function searchResults(sections: PickerSection[], term: string, strings: Pick<PickerStrings, 'search' | 'custom'>, limit: number = MAX_RESULTS): PickerSection[] {
  // Kaomoji and symbols search their own names.
  if (sections.some((s) => s.text)) {
    const items = sections.flatMap((s) => (s.text ? s.items : []));

    return [{ slug: 'search', label: strings.search, text: true, items: searchText(items, term, limit) }];
  }

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
    features: readFeatures(safeJson(data('features') ?? '{}', {})),
    render: parseRender(data('render')),
    // A theme the page fixed: the option, or Blade's data-theme on the mount point.
    theme: ((t) => (t === 'light' || t === 'dark' ? t : 'auto'))(data('theme') ?? element.getAttribute('data-theme')),
    shortcut: element.hasAttribute(`${ATTR}-shortcut`) ? data('shortcut') || null : DEFAULT_SHORTCUT,
  };
}

const PLACEMENTS = new Set(['auto', 'top', 'bottom', 'start', 'end', 'top-start', 'top-end', 'bottom-start', 'bottom-end', 'start-start', 'start-end', 'end-start', 'end-end']);

/** A render mode from an attribute or prop; anything unknown is 'auto'. */
export function parseRender(value: unknown): RenderMode {
  return value === 'native' || value === 'image' ? value : 'auto';
}

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
  /**
   * Each data block parsed once, however many pickers read it: ten pickers on a page share one ~400 KB
   * JSON.parse. Keyed by the element, so a block a morph replaces is read afresh.
   */
  private static parsed = new WeakMap<Element, PickerPayload>();

  constructor(private readonly payload: PickerPayload | string) {}

  async load(): Promise<PickerPayload> {
    if (typeof this.payload !== 'string') {
      return this.payload;
    }

    const block = document.getElementById(this.payload);

    if (!block) {
      throw new Error(`picker payload: no element #${this.payload}`);
    }

    let parsed = StaticSource.parsed.get(block);

    if (!parsed) {
      parsed = JSON.parse(block.textContent ?? '{}') as PickerPayload;
      StaticSource.parsed.set(block, parsed);
    }

    return parsed;
  }
}

/** How many cells a grid draws before the browser first paints; the rest follow in idle time. */
export const FIRST_PAINT_CELLS = 200;

/** Runs a callback when the browser is idle, or soon after where it cannot say (Safari, tests). */
function whenIdle(callback: () => void): void {
  const idle = (globalThis as { requestIdleCallback?: (cb: () => void, options?: { timeout: number }) => number }).requestIdleCallback;

  if (idle) {
    idle(callback, { timeout: 120 });
  } else {
    setTimeout(callback, 1);
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

// ---- per-person tones ------------------------------------------------------------------------------------

/**
 * The toned forms an emoji offers. One person: tone 0 (none) to 5. Two people (🤝, couples): a tone for each,
 * 1 to 5, where the same tone on both is keyed by the one tone ("3") and different tones by the pair
 * ("3-5"). `form()` answers the hexcode for a choice, or null when the payload does not offer it (the policy
 * or the version cap removed it).
 */
export function toneForms(item: PickerEmoji): { people: 0 | 1 | 2; form: (first: number, second?: number) => string | null } {
  const skins = item.skins ?? {};
  const keys = Object.keys(skins);
  const people: 0 | 1 | 2 = keys.length === 0 ? 0 : keys.some((key) => key.includes('-')) ? 2 : 1;

  return {
    people,
    form: (first, second) => {
      if (people === 2) {
        const b = second ?? first;

        return first === b ? (skins[String(first)] ?? skins[`${first}-${first}`] ?? null) : (skins[`${first}-${b}`] ?? null);
      }

      return first === 0 ? (item.base === false ? null : item.hexcode) : (skins[String(first)] ?? null);
    },
  };
}

export interface ToneMenuOptions {
  strings: PickerStrings;
  /** The tone to start from (the picker's current one). */
  tone: number;
  mode: RenderMode;
  set: PickerImageSet | null | undefined;
  rtl?: boolean;
  onPick: (hexcode: string) => void;
  onClose?: () => void;
}

export interface AnchoredOptions {
  /** The element the menu points at; positions follow it as it moves. */
  anchor: Element;
  /** Where to point instead of the anchor's box (the text caret inside a field), recomputed on every move. */
  rect?: () => Rect;
  /** Where the menu lives in the DOM (inside the picker, so focus and clicks count as inside it). */
  container: HTMLElement;
  /** The caret element inside the menu, moved to point at the anchor. */
  arrow?: HTMLElement | null;
  placement?: Placement;
  rtl?: boolean;
  /** Close on a pointer press outside the menu (default true). */
  dismissOnOutside?: boolean;
  onClose?: () => void;
}

/**
 * Shows a small menu beside an anchor, the way the tone menu, the settings menu and the shortcode suggestions
 * all appear: in the top layer where the browser has one, placed by computePosition() and kept there by
 * autoUpdate(), its caret pointing at the anchor, and closed by a press outside it. Returns the function
 * that closes it.
 */
export function openAnchored(menu: HTMLElement, options: AnchoredOptions): () => void {
  const { anchor, container, arrow = null } = options;
  const doc = anchor.ownerDocument;
  let close = (): void => {};
  const onOutside = (event: Event): void => {
    if (!menu.contains(event.target as Node)) close();
  };

  container.append(menu);

  const popover = menu as HTMLElement & { showPopover?: () => void; hidePopover?: () => void };

  if (typeof popover.showPopover === 'function') {
    menu.setAttribute('popover', 'manual');

    try {
      popover.showPopover();
    } catch {
      // Fixed in place instead.
    }
  }

  const place = (): void => {
    const view = doc.defaultView;
    const at = options.rect ? options.rect() : (() => {
      const box = anchor.getBoundingClientRect();

      return { x: box.left, y: box.top, width: box.width, height: box.height };
    })();
    const viewport = view?.visualViewport;
    const result = computePosition(
      at,
      { width: menu.offsetWidth, height: menu.offsetHeight },
      { x: viewport?.offsetLeft ?? 0, y: viewport?.offsetTop ?? 0, width: viewport?.width ?? view?.innerWidth ?? 0, height: viewport?.height ?? view?.innerHeight ?? 0 },
      { placement: options.placement ?? 'top', offset: 8, rtl: options.rtl },
    );

    menu.style.left = `${result.x}px`;
    menu.style.top = `${result.y}px`;
    menu.setAttribute('data-placement', `${result.side}-${result.align}`);

    if (arrow) {
      arrow.hidden = result.arrow === null;
      arrow.style.left = result.side === 'top' || result.side === 'bottom' ? `${result.arrow ?? 0}px` : '';
      arrow.style.top = result.side === 'left' || result.side === 'right' ? `${result.arrow ?? 0}px` : '';
    }
  };
  const stop = autoUpdate(anchor, menu, place);
  // Deferred, so the long press or right click that opened it does not close it.
  const timer = options.dismissOnOutside === false ? null : setTimeout(() => doc.addEventListener('pointerdown', onOutside, true), 0);
  let closed = false;

  close = (): void => {
    if (closed) return;
    closed = true;

    if (timer !== null) clearTimeout(timer);

    stop();
    doc.removeEventListener('pointerdown', onOutside, true);

    try {
      popover.hidePopover?.();
    } catch {
      // Already hidden.
    }

    menu.remove();
    options.onClose?.();
  };

  return close;
}

/**
 * A small popover beside an emoji for choosing its tone for this one pick, as phones do on a long press:
 * six toned forms for one person, or a tone for each person in 🤝 and couples with the result previewed.
 * It is placed with computePosition() and carries the same caret as the picker. Arrow keys move, Enter or
 * Space picks, Escape closes and returns focus to the emoji. Returns the function that closes it.
 */
export function openToneMenu(anchor: HTMLElement, container: HTMLElement, item: PickerEmoji, options: ToneMenuOptions): () => void {
  const { strings: s } = options;
  const { people, form } = toneForms(item);
  const doc = anchor.ownerDocument;
  const make = <K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Record<string, string | boolean | null | undefined> = {}, text?: string): HTMLElementTagNameMap[K] => {
    const node = doc.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
      if (value !== null && value !== undefined && value !== false) node.setAttribute(name, value === true ? '' : String(value));
    }

    if (text !== undefined) node.textContent = text;

    return node;
  };
  const face = (hexcode: string): Node => {
    const url = drawsImage(item, hexcode, options.mode, options.set);

    return url ? make('img', { src: url, alt: '', class: `${PREFIX} ${PREFIX}-image`, draggable: 'false' }) : doc.createTextNode(charOf(hexcode));
  };

  const menu = make('div', { class: `${PREFIX}-picker-tonemenu`, role: 'dialog', 'aria-label': `${s.tone}: ${item.name}` });
  const arrow = make('div', { class: `${PREFIX}-picker-arrow`, 'aria-hidden': 'true' });
  menu.append(arrow);

  let close = (): void => {};

  if (people === 2) {
    const choice = [Math.max(1, options.tone), Math.max(1, options.tone)];
    const result = make('button', { type: 'button', class: `${PREFIX}-picker-cell ${PREFIX}-picker-tonemenu-result`, 'aria-label': item.name });
    const update = (): void => {
      const hexcode = form(choice[0] ?? 1, choice[1] ?? 1);
      result.replaceChildren(hexcode ? face(hexcode) : doc.createTextNode('—'));
      result.disabled = hexcode === null;
      result.dataset.hexcode = hexcode ?? '';
    };

    for (const person of [0, 1]) {
      const row = make('div', { class: `${PREFIX}-picker-tones`, role: 'radiogroup', 'aria-label': `${s.tone} ${person + 1}` });

      for (let tone = 1; tone <= 5; tone++) {
        const radio = make('button', { type: 'button', role: 'radio', class: `${PREFIX}-picker-tone`, 'aria-checked': String(choice[person] === tone), tabindex: choice[person] === tone ? '0' : '-1', 'aria-label': s.tones[tone] ?? '', [`${ATTR}-tone`]: String(tone) }, TONE_SWATCHES[tone]);

        radio.addEventListener('click', () => {
          choice[person] = tone;

          for (const other of row.querySelectorAll<HTMLElement>('[role="radio"]')) {
            const on = other === radio;
            other.setAttribute('aria-checked', String(on));
            other.tabIndex = on ? 0 : -1;
          }

          update();
        });
        row.append(radio);
      }

      menu.append(row);
    }

    update();
    result.addEventListener('click', () => {
      if (result.dataset.hexcode) {
        options.onPick(result.dataset.hexcode);
        close();
      }
    });
    menu.append(result);
  } else {
    const row = make('div', { class: `${PREFIX}-picker-tonemenu-row`, role: 'group', 'aria-label': s.tone });

    for (let tone = 0; tone <= 5; tone++) {
      const hexcode = form(tone);

      if (hexcode === null) continue;

      const button = make('button', { type: 'button', class: `${PREFIX}-picker-cell`, 'aria-label': `${item.name}, ${s.tones[tone] ?? ''}`, [`${ATTR}-hexcode`]: hexcode });
      button.append(face(hexcode));
      button.addEventListener('click', () => {
        options.onPick(hexcode);
        close();
      });
      row.append(button);
    }

    menu.append(row);
  }

  const focusables = (): HTMLElement[] => [...menu.querySelectorAll<HTMLElement>('button:not([disabled])')];
  const onKey = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close();
      anchor.focus();

      return;
    }

    const all = focusables();
    const index = all.indexOf(doc.activeElement as HTMLElement);
    const next = rovingIndex(all.length, Math.max(0, index), event.key, options.rtl);

    if (next !== null && event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
      event.preventDefault();
      all[next]?.focus();
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
      event.preventDefault();
      all[(index + (event.key === 'ArrowDown' ? 5 : all.length - 5)) % all.length]?.focus();
    }
  };

  menu.addEventListener('keydown', onKey);
  close = openAnchored(menu, { anchor, container, arrow, placement: 'top', rtl: options.rtl, onClose: options.onClose });

  (menu.querySelector<HTMLElement>('[role="radio"][tabindex="0"]') ?? focusables()[0])?.focus();

  return close;
}

/**
 * Opens the tone menu for an emoji cell on a right click, the context-menu key or Shift+F10, or a long press
 * (half a second without moving), which is how phones offer tones. Delegated on the grid, so both pickers
 * bind it once. `resolve` answers the emoji a cell shows, or null for one with no tones (then the browser's
 * own context menu is left alone). Returns the function that unbinds it.
 */
export function bindToneMenu(body: HTMLElement, resolve: (cell: HTMLElement) => PickerEmoji | null, open: (cell: HTMLElement, item: PickerEmoji) => void): () => void {
  let timer: ReturnType<typeof setTimeout> | null = null;
  let start: { x: number; y: number } | null = null;
  let fired = false;
  const cellOf = (event: Event): HTMLElement | null => (event.target as Element | null)?.closest?.<HTMLElement>(`[${ATTR}-hexcode]`) ?? null;
  const cancel = (): void => {
    if (timer !== null) clearTimeout(timer);
    timer = null;
    start = null;
  };
  const tryOpen = (cell: HTMLElement | null, event: Event): boolean => {
    const item = cell ? resolve(cell) : null;

    if (!cell || !item) return false;

    event.preventDefault();
    open(cell, item);

    return true;
  };

  const onContext = (event: MouseEvent): void => {
    tryOpen(cellOf(event), event);
  };
  const onKey = (event: KeyboardEvent): void => {
    if (event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey)) {
      tryOpen(cellOf(event), event);
    }
  };
  const onDown = (event: PointerEvent): void => {
    if (event.pointerType === 'mouse') return;

    const cell = cellOf(event);

    if (!cell || !resolve(cell)) return;

    fired = false;
    start = { x: event.clientX, y: event.clientY };
    timer = setTimeout(() => {
      timer = null;
      fired = tryOpen(cell, event);
    }, 500);
  };
  const onMove = (event: PointerEvent): void => {
    if (start && Math.hypot(event.clientX - start.x, event.clientY - start.y) > 8) cancel();
  };
  // A long press that opened the menu must not also pick the emoji it was held on.
  const onClick = (event: MouseEvent): void => {
    if (fired) {
      fired = false;
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  };

  body.addEventListener('contextmenu', onContext);
  body.addEventListener('keydown', onKey);
  body.addEventListener('pointerdown', onDown);
  body.addEventListener('pointermove', onMove);
  body.addEventListener('pointerup', cancel);
  body.addEventListener('pointercancel', cancel);
  body.addEventListener('click', onClick, true);

  return () => {
    cancel();
    body.removeEventListener('contextmenu', onContext);
    body.removeEventListener('keydown', onKey);
    body.removeEventListener('pointerdown', onDown);
    body.removeEventListener('pointermove', onMove);
    body.removeEventListener('pointerup', cancel);
    body.removeEventListener('pointercancel', cancel);
    body.removeEventListener('click', onClick, true);
  };
}

// ---- keyboard shortcuts -------------------------------------------------------------------------------------

/** A parsed key combination: "Mod+Shift+." is ⌘⇧. on Apple platforms and Ctrl+Shift+. elsewhere. */
export interface Shortcut {
  /** The physical key, as KeyboardEvent.code ("Period", "KeyE", "Digit1"), so a layout's shifted symbol still matches. */
  code: string;
  label: string;
  mod: boolean;
  ctrl: boolean;
  meta: boolean;
  alt: boolean;
  shift: boolean;
}

/** The field shortcut that opens a picker, unless configured otherwise. */
export const DEFAULT_SHORTCUT = 'Mod+Shift+.';

const PUNCTUATION: Record<string, string> = { '.': 'Period', ',': 'Comma', ';': 'Semicolon', '/': 'Slash', "'": 'Quote', '[': 'BracketLeft', ']': 'BracketRight', '-': 'Minus', '=': 'Equal', '`': 'Backquote', '\\': 'Backslash' };

/** Whether the platform's primary modifier is ⌘. */
export function isApple(nav: { platform?: string; userAgent?: string } | undefined = globalThis.navigator): boolean {
  return /Mac|iPhone|iPad|iPod/i.test(nav?.platform ?? nav?.userAgent ?? '');
}

/**
 * Parses "Mod+Shift+.", "Ctrl+Alt+E", "Alt+1". Mod is ⌘ on Apple platforms and Ctrl elsewhere. Null for an
 * empty or unreadable string, which turns the shortcut off.
 */
export function parseShortcut(text: string | null | undefined): Shortcut | null {
  const parts = String(text ?? '').split('+').map((part) => part.trim()).filter(Boolean);
  const key = parts.pop();

  if (!key) {
    return null;
  }

  const has = (name: string): boolean => parts.some((part) => part.toLowerCase() === name);
  const code = /^[a-z]$/i.test(key) ? `Key${key.toUpperCase()}` : /^\d$/.test(key) ? `Digit${key}` : key.toLowerCase() === 'space' ? 'Space' : (PUNCTUATION[key] ?? null);

  if (code === null || parts.some((part) => !['mod', 'ctrl', 'control', 'meta', 'cmd', 'alt', 'option', 'shift'].includes(part.toLowerCase()))) {
    return null;
  }

  return { code, label: key.length === 1 ? key.toUpperCase() : key, mod: has('mod'), ctrl: has('ctrl') || has('control'), meta: has('meta') || has('cmd'), alt: has('alt') || has('option'), shift: has('shift') };
}

/** Whether a key event is the shortcut, modifiers exactly. */
export function matchesShortcut(event: Pick<KeyboardEvent, 'code' | 'ctrlKey' | 'metaKey' | 'altKey' | 'shiftKey'>, shortcut: Shortcut, apple: boolean = isApple()): boolean {
  const ctrl = shortcut.ctrl || (shortcut.mod && !apple);
  const meta = shortcut.meta || (shortcut.mod && apple);

  return event.code === shortcut.code && event.ctrlKey === ctrl && event.metaKey === meta && event.altKey === shortcut.alt && event.shiftKey === shortcut.shift;
}

/** How a shortcut is written for the user: ⌘⇧. on Apple platforms, Ctrl+Shift+. elsewhere. */
export function shortcutLabel(shortcut: Shortcut, apple: boolean = isApple()): string {
  if (apple) {
    return `${shortcut.ctrl ? '⌃' : ''}${shortcut.alt ? '⌥' : ''}${shortcut.shift ? '⇧' : ''}${shortcut.meta || shortcut.mod ? '⌘' : ''}${shortcut.label}`;
  }

  return [shortcut.ctrl || shortcut.mod ? 'Ctrl' : '', shortcut.meta ? 'Win' : '', shortcut.alt ? 'Alt' : '', shortcut.shift ? 'Shift' : '', shortcut.label].filter(Boolean).join('+');
}

/** The keys a picker answers to, for the shortcut list in its settings: [what it does, the keys]. */
export function shortcutList(strings: PickerStrings, open: Shortcut | null, apple: boolean = isApple()): Array<[string, string]> {
  const s = { ...DEFAULT_STRINGS, ...strings };

  return [
    ...(open ? [[s.kbdOpen ?? '', shortcutLabel(open, apple)] as [string, string]] : []),
    [s.kbdSearch ?? '', '/'],
    [s.kbdCategory ?? '', apple ? '⌥1 – ⌥9' : 'Alt+1 – Alt+9'],
    [s.kbdMove ?? '', '← ↑ → ↓ · PgUp PgDn · Home End'],
    [s.kbdTone ?? '', apple ? '⇧F10 · ⌃-click' : 'Shift+F10'],
    [s.kbdShortcuts ?? '', '?'],
    [s.kbdClose ?? '', 'Esc'],
  ];
}

/** Opens a picker when its shortcut is pressed in a field. Returns the function that unbinds it. */
export function bindShortcut(field: EventTarget, shortcut: Shortcut | null, open: () => void): () => void {
  if (!shortcut) {
    return () => {};
  }

  const onKey = (event: Event): void => {
    if (matchesShortcut(event as KeyboardEvent, shortcut)) {
      event.preventDefault();
      open();
    }
  };

  field.addEventListener('keydown', onKey);

  return () => field.removeEventListener('keydown', onKey);
}

// ---- the settings menu ------------------------------------------------------------------------------------

export interface SettingsMenuOptions {
  strings: PickerStrings;
  /** The theme in effect; the theme row is left out when the page fixed one (null). */
  theme: PickerTheme | null;
  onTheme: (theme: PickerTheme) => void;
  /** Whether there are recents to clear (the button is disabled otherwise). */
  recents: boolean;
  onClearRecents: () => void;
  shortcuts: Array<[string, string]>;
  /** Open on the shortcut list (the ? key). */
  focusShortcuts?: boolean;
  rtl?: boolean;
  onClose?: () => void;
}

/**
 * The settings menu behind the picker's gear button: the theme (Auto, Light, Dark) when the page has not
 * fixed one, clearing Frequently used, and the keyboard shortcuts. A dialog beside the button, with the
 * picker's caret; Escape closes it and returns focus to the button.
 */
export function openSettingsMenu(anchor: HTMLElement, container: HTMLElement, options: SettingsMenuOptions): () => void {
  const s = { ...DEFAULT_STRINGS, ...options.strings };
  const doc = anchor.ownerDocument;
  const make = <K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Record<string, string | boolean | null | undefined> = {}, text?: string): HTMLElementTagNameMap[K] => {
    const node = doc.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
      if (value !== null && value !== undefined && value !== false) node.setAttribute(name, value === true ? '' : String(value));
    }

    if (text !== undefined) node.textContent = text;

    return node;
  };
  const menu = make('div', { class: `${PREFIX}-picker-settings`, role: 'dialog', 'aria-label': s.settings });
  const arrow = make('div', { class: `${PREFIX}-picker-arrow`, 'aria-hidden': 'true' });
  let close = (): void => {};

  menu.append(arrow);

  if (options.theme !== null) {
    const label = make('div', { class: `${PREFIX}-picker-settings-label`, id: `${PREFIX}-settings-theme-${Math.random().toString(36).slice(2, 8)}` }, s.theme);
    const group = make('div', { class: `${PREFIX}-picker-settings-themes`, role: 'radiogroup', 'aria-labelledby': label.id });
    const choices: Array<[PickerTheme, string]> = [['auto', s.themeAuto ?? 'Auto'], ['light', s.themeLight ?? 'Light'], ['dark', s.themeDark ?? 'Dark']];

    for (const [theme, text] of choices) {
      const checked = theme === options.theme;
      const radio = make('button', { type: 'button', role: 'radio', class: `${PREFIX}-picker-settings-theme`, 'aria-checked': String(checked), tabindex: checked ? '0' : '-1', [`${ATTR}-theme`]: theme }, text);

      radio.addEventListener('click', () => {
        for (const other of group.querySelectorAll<HTMLElement>('[role="radio"]')) {
          const on = other === radio;
          other.setAttribute('aria-checked', String(on));
          other.tabIndex = on ? 0 : -1;
        }

        options.onTheme(theme);
      });
      group.append(radio);
    }

    group.addEventListener('keydown', (event) => {
      const radios = [...group.querySelectorAll<HTMLElement>('[role="radio"]')];
      const next = rovingIndex(radios.length, radios.indexOf(doc.activeElement as HTMLElement), event.key, options.rtl);

      if (next !== null) {
        event.preventDefault();
        radios[next]?.focus();
        radios[next]?.click();
      }
    });
    menu.append(label, group);
  }

  const clear = make('button', { type: 'button', class: `${PREFIX}-picker-settings-clear`, disabled: !options.recents }, s.clearRecents);

  clear.addEventListener('click', () => {
    options.onClearRecents();
    clear.disabled = true;
  });
  menu.append(clear);

  const heading = make('div', { class: `${PREFIX}-picker-settings-label`, id: `${PREFIX}-settings-keys-${Math.random().toString(36).slice(2, 8)}` }, s.kbdShortcuts);
  const list = make('dl', { class: `${PREFIX}-picker-shortcuts`, 'aria-labelledby': heading.id, tabindex: '-1' });

  for (const [what, keys] of options.shortcuts) {
    list.append(make('dt', {}, what));
    const dd = make('dd');
    dd.append(make('kbd', {}, keys));
    list.append(dd);
  }

  menu.append(heading, list);
  menu.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close();
      anchor.focus();
    }
  });
  close = openAnchored(menu, { anchor, container, arrow, placement: 'top-end', rtl: options.rtl, onClose: options.onClose });

  const first = options.focusShortcuts ? list : (menu.querySelector<HTMLElement>('[role="radio"][tabindex="0"]') ?? clear);
  first.focus();

  return close;
}

// ---- :shortcode autocomplete ---------------------------------------------------------------------------------

/**
 * Where the text caret is in an input or a textarea, in viewport coordinates: the box a mirror of the field
 * puts after the text before the caret. The mirror copies every style that moves text, so wrapping, padding
 * and scrolling come out the same.
 */
export function caretRect(field: HTMLInputElement | HTMLTextAreaElement): Rect {
  const doc = field.ownerDocument;
  const view = doc.defaultView;
  const box = field.getBoundingClientRect();
  const style = view?.getComputedStyle(field);
  const mirror = doc.createElement('div');
  const marker = doc.createElement('span');
  const position = field.selectionEnd ?? field.value.length;
  const copy = ['boxSizing', 'width', 'height', 'overflowX', 'overflowY', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'fontStyle', 'fontVariant', 'fontWeight', 'fontStretch', 'fontSize', 'lineHeight', 'fontFamily', 'textAlign', 'textTransform', 'textIndent', 'letterSpacing', 'wordSpacing', 'tabSize', 'direction'] as const;

  if (style) {
    for (const property of copy) {
      mirror.style[property] = style[property];
    }
  }

  mirror.style.position = 'absolute';
  mirror.style.visibility = 'hidden';
  mirror.style.top = '0';
  mirror.style.left = '-9999px';
  mirror.style.whiteSpace = field instanceof HTMLTextAreaElement ? 'pre-wrap' : 'pre';
  mirror.style.overflowWrap = 'break-word';
  mirror.textContent = field.value.slice(0, position);
  marker.textContent = field.value.slice(position) || '.';
  mirror.append(marker);
  doc.body.append(mirror);

  const lineHeight = parseFloat(style?.lineHeight ?? '') || parseFloat(style?.fontSize ?? '') * 1.2 || 16;
  const rect = {
    x: box.left + marker.offsetLeft - field.scrollLeft,
    y: box.top + marker.offsetTop - field.scrollTop,
    width: 1,
    height: lineHeight,
  };

  mirror.remove();

  return rect;
}

export interface AutocompleteOptions {
  /** Where the suggestion list lives in the DOM (the picker's root). */
  container: HTMLElement;
  /** The emoji and custom emoji to suggest from, as the picker has them now. */
  source: () => { emoji: PickerEmoji[]; custom: PickerCustom[]; data: PickerPayload | null };
  /** How a suggestion is drawn and what it inserts: the picker's tone, render mode and image set. */
  tone: () => number;
  render: () => { mode: RenderMode; set: PickerImageSet | null };
  strings: PickerStrings;
  /** Letters after the colon before suggestions appear (default 2). */
  min?: number;
  /** Suggestions shown at most (default 8). */
  limit?: number;
  rtl?: () => boolean;
  /** Called with what was inserted, after the field has it. */
  onPick: (detail: SelectDetail, base: string | null) => void;
}

/**
 * Suggests emoji as the user types a shortcode, the way Slack and Discord do: ":smi" in the field opens a list
 * beside the text caret (with the picker's caret pointing at it). Arrow keys move, Enter or Tab inserts the
 * emoji in place of the code, Escape dismisses until the next code. The field keeps focus throughout, and
 * gets aria-autocomplete, aria-expanded, aria-controls and aria-activedescendant while the list is open.
 * Inputs and textareas only. Returns the function that detaches it.
 */
export function attachAutocomplete(field: HTMLInputElement | HTMLTextAreaElement, options: AutocompleteOptions): () => void {
  const doc = field.ownerDocument;
  const min = options.min ?? 2;
  const limit = options.limit ?? 8;
  const id = `${PREFIX}-suggest-${Math.random().toString(36).slice(2, 9)}`;
  let close: (() => void) | null = null;
  let list: HTMLElement | null = null;
  let items: Array<{ detail: () => SelectDetail; base: string | null; node: HTMLElement }> = [];
  let active = 0;
  let range: [number, number] | null = null;
  let dismissedAt: number | null = null;

  const reset = (): void => {
    close?.();
    close = null;
    list = null;
    items = [];
    range = null;

    for (const name of ['aria-expanded', 'aria-controls', 'aria-activedescendant']) field.removeAttribute(name);
  };
  const highlight = (index: number): void => {
    active = (index + items.length) % items.length;
    items.forEach((item, i) => item.node.setAttribute('aria-selected', String(i === active)));
    field.setAttribute('aria-activedescendant', items[active]?.node.id ?? '');
  };
  const pick = (index: number): void => {
    const item = items[index];
    const span = range;

    if (!item || !span) return;

    const detail = item.detail();
    reset();
    field.setSelectionRange(span[0], span[1]);
    insertText(field, detail.emoji, true);
    options.onPick(detail, item.base);
  };

  const update = (): void => {
    const caret = field.selectionEnd ?? field.value.length;
    const before = field.value.slice(0, caret);
    const match = /(^|[\s([{])(:)([\p{L}\p{N}_+-]+)$/u.exec(before);

    if (!match || (match[3] ?? '').length < min || dismissedAt === caret - (match[3] ?? '').length - 1) {
      reset();

      return;
    }

    const term = match[3] ?? '';
    const start = caret - term.length - 1;
    const { emoji, custom, data } = options.source();
    const tone = options.tone();
    const { mode, set } = options.render();
    const found = [
      ...searchCustom(custom, term).slice(0, 2).map((c) => ({ text: '', image: c.image, label: c.label, code: customCode(c.name, data), base: null as string | null, detail: (): SelectDetail => ({ emoji: customCode(c.name, data), hexcode: null, name: c.label, shortcode: c.name, custom: true }) })),
      ...search(emoji, term, limit).map((e) => {
        const hexcode = withTone(e, tone);

        return { text: charOf(hexcode), image: drawsImage(e, hexcode, mode, set), label: e.name, code: e.shortcode ? customCode(e.shortcode, data) : '', base: e.hexcode as string | null, detail: (): SelectDetail => ({ emoji: charOf(hexcode), hexcode, name: e.name, shortcode: e.shortcode, custom: false }) };
      }),
    ].slice(0, limit);

    if (found.length === 0) {
      reset();

      return;
    }

    range = [start, caret];

    if (!list) {
      list = doc.createElement('div');
      list.className = `${PREFIX}-picker-suggest`;
      list.id = id;
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-label', options.strings.search ?? DEFAULT_STRINGS.search);
      const arrow = doc.createElement('div');
      arrow.className = `${PREFIX}-picker-arrow`;
      arrow.setAttribute('aria-hidden', 'true');
      list.append(arrow);
      // A press on a suggestion must not take focus from the field.
      list.addEventListener('pointerdown', (event) => event.preventDefault());
      close = openAnchored(list, { anchor: field, rect: () => caretRect(field), container: options.container, arrow, placement: 'bottom-start', rtl: options.rtl?.(), dismissOnOutside: true, onClose: () => { list = null; close = null; } });
    }

    for (const old of list.querySelectorAll('[role="option"]')) old.remove();
    items = found.map((entry, index) => {
      const node = doc.createElement('div');
      node.id = `${id}-${index}`;
      node.className = `${PREFIX}-picker-suggestion`;
      node.setAttribute('role', 'option');
      const glyph = doc.createElement('span');
      glyph.className = `${PREFIX}-picker-suggestion-glyph`;

      if (entry.image) {
        const img = doc.createElement('img');
        img.src = entry.image;
        img.alt = '';
        img.className = `${PREFIX} ${PREFIX}-image`;
        glyph.append(img);
      } else {
        glyph.textContent = entry.text;
      }

      const name = doc.createElement('span');
      name.className = `${PREFIX}-picker-suggestion-name`;
      name.textContent = entry.code || entry.label;
      node.setAttribute('aria-label', entry.label);
      node.append(glyph, name);
      node.addEventListener('click', () => pick(index));
      list?.append(node);

      return { detail: entry.detail, base: entry.base, node };
    });

    field.setAttribute('aria-autocomplete', 'list');
    field.setAttribute('aria-expanded', 'true');
    field.setAttribute('aria-controls', id);
    highlight(0);
  };

  const onKey = (event: KeyboardEvent): void => {
    if (!list || items.length === 0) return;

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      highlight(active + (event.key === 'ArrowDown' ? 1 : -1));
    } else if (event.key === 'Enter' || event.key === 'Tab') {
      event.preventDefault();
      pick(active);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      dismissedAt = range?.[0] ?? null;
      reset();
    }
  };
  const onBlur = (): void => {
    // Let a click on a suggestion land first.
    setTimeout(() => {
      if (doc.activeElement !== field) reset();
    }, 120);
  };

  field.addEventListener('input', update);
  field.addEventListener('keydown', onKey as EventListener);
  field.addEventListener('blur', onBlur);

  return () => {
    reset();
    field.removeAttribute('aria-autocomplete');
    field.removeEventListener('input', update);
    field.removeEventListener('keydown', onKey as EventListener);
    field.removeEventListener('blur', onBlur);
  };
}

// ---- tabs ------------------------------------------------------------------------------------------------

/** The settings button's outline gear, on the same 24×24 grid as the tab icons. */
export const GEAR_ICON: readonly string[] = [
  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z',
];

/** An outline icon from SVG path data, built with createElementNS so it stays CSP-safe. */
export function icon(paths: readonly string[], size = 20): SVGSVGElement {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

  for (const [name, value] of Object.entries({ viewBox: '0 0 24 24', width: String(size), height: String(size), fill: 'none', stroke: 'currentColor', 'stroke-width': '1.75', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' })) {
    svg.setAttribute(name, value);
  }

  for (const d of paths) {
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', d);
    svg.append(path);
  }

  return svg;
}

/**
 * What a category tab shows: the group's outline icon when there is one, else its first emoji, else a short
 * label (kaomoji and symbol groups). Built with createElementNS, so it stays CSP-safe.
 */
export function tabFace(section: PickerSection): Node {
  const paths = TAB_ICONS[section.slug];

  if (paths) {
    return icon(paths);
  }

  const first = section.items[0];

  if (section.text) {
    return document.createTextNode(first ? (first as PickerText).text.slice(0, 4) : section.label.slice(0, 2));
  }

  return document.createTextNode(first && !section.custom ? charOf((first as PickerEmoji).pick ?? (first as PickerEmoji).hexcode) : '★');
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

type ResolvedOptions = Required<Omit<PickerOptions, 'source' | 'target' | 'store' | 'features'>> & Pick<PickerOptions, 'source' | 'target' | 'store'> & { features: PickerFeatures };
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
  private kinds!: HTMLDivElement;
  private preview: HTMLDivElement | null = null;
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
  private kind: PickerKind = 'emoji';
  /** The payload as loaded, before the device's cap: what a change of image set re-caps from. */
  private raw: PickerPayload | null = null;
  private support: { version: string | null; flags: boolean | null } = { version: null, flags: null };
  private closeToneMenu: (() => void) | null = null;
  private switcher: HTMLSelectElement | null = null;
  private gear: HTMLButtonElement | null = null;
  private closeSettings: (() => void) | null = null;
  /** A popover's grid is built the first time it opens, not on page load. */
  private pendingRender = false;
  /** The sections still to draw in idle time, and which render they belong to. */
  private rest: PickerSection[] = [];
  private bodyToken = 0;
  private finishBody: (() => void) | null = null;

  private readonly imageFailed = (event: Event): void => {
    const image = event.target as Element | null;
    const cell = image instanceof HTMLImageElement ? image.closest<HTMLElement>(`[${ATTR}-hexcode]`) : null;

    if (image && cell) {
      image.replaceWith(charOf(cell.getAttribute(`${ATTR}-hexcode`) ?? ''));
    }
  };
  private stopSpy: (() => void) | null = null;

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
      render: 'auto',
      theme: 'auto',
      shortcut: DEFAULT_SHORTCUT,
      ...options,
      features: readFeatures(options.features),
    } as ResolvedOptions;
    this.options.render = parseRender(this.options.render);
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

  /** Fixes the colour scheme ('light', 'dark'), or follows the OS and the page again ('auto'). */
  theme(theme: PickerTheme): this {
    this.options.theme = theme === 'light' || theme === 'dark' ? theme : 'auto';
    this.applyTheme();

    return this;
  }

  /**
   * The theme in effect: one the page fixed (the option) wins; otherwise the one the user chose in the
   * settings menu, remembered per user-key; otherwise 'auto'.
   */
  private get themeInEffect(): PickerTheme {
    if (this.options.theme !== 'auto') {
      return this.options.theme;
    }

    const chosen = this.options.features.settings ? this.store.get('theme') : null;

    return chosen === 'light' || chosen === 'dark' ? chosen : 'auto';
  }

  private applyTheme(): void {
    const theme = this.themeInEffect;

    if (theme === 'auto') {
      this.root?.removeAttribute('data-theme');
    } else {
      this.root?.setAttribute('data-theme', theme);
    }
  }
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
    this.closeSettings?.();
    this.closeToneMenu?.();
    this.stopSpy?.();
    this.stopSpy = null;
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
    this.panel.hidden = false;

    if (this.recentStale || this.pendingRender) {
      this.recentStale = false;
      this.memo = null;
      this.renderWhenShown();
    }

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

      const auto = this.options.maxVersion === 'auto';
      const [version, flags] = await Promise.all([auto ? detectMaxVersionOnce() : Promise.resolve(this.options.maxVersion ?? null), payload.images ? detectFlagsOnce() : Promise.resolve(null)]);

      if (id !== this.loads) {
        return;
      }

      this.raw = payload;
      this.support = { version, flags };
      this.data = this.capped();
      this.byHex = null;
      this.memo = null;
      this.status.textContent = '';
      this.renderWhenShown();
      this.emit('ready', { picker: this });
    } catch (error) {
      if (id !== this.loads) {
        return;
      }

      this.status.textContent = this.strings.failed;
      this.emit('error', { error });
    }
  }

  /** The image set in use: the one chosen in the set switcher, else the payload's. Null when there is none. */
  private get imageSet(): PickerImageSet | null {
    const chosen = this.options.features.setSwitcher ? this.store.get('set') : null;

    if (chosen === 'native') {
      return this.raw?.images ?? null;
    }

    return (this.raw?.imageSets ?? []).find((set) => set.set === chosen) ?? this.raw?.images ?? null;
  }

  /** How cells draw: the option, unless the set switcher picked a set (then every emoji from it). */
  private get renderMode(): RenderMode {
    const chosen = this.options.features.setSwitcher ? this.store.get('set') : null;

    return typeof chosen === 'string' && chosen !== 'native' && (this.raw?.imageSets ?? []).some((set) => set.set === chosen) ? 'image' : this.options.render;
  }

  /** The payload capped for this device, falling back to images where the mode allows. */
  private capped(): PickerPayload {
    const raw = this.raw ?? { groups: [] };
    const mode = this.renderMode;

    return capPayload(raw, this.support.version, undefined, mode === 'native' ? {} : { set: this.imageSet, flags: this.support.flags });
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
      this.renderWhenShown();
    }

    return this;
  }

  /** Draws now when the picker is visible; a closed popover is drawn when it next opens. */
  private renderWhenShown(): void {
    if (!this.options.inline && this.panel.hidden) {
      this.pendingRender = true;

      return;
    }

    this.pendingRender = false;
    this.render();
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

    // The field's shortcut opens the picker (an inline one takes focus in its search instead).
    this.targetCleanup.push(bindShortcut(target, parseShortcut(this.options.shortcut), () => {
      if (this.options.inline) {
        this.searchInput.focus();
      } else {
        this.open();
      }
    }));

    if (this.options.features.autocomplete && (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)) {
      this.targetCleanup.push(attachAutocomplete(target, {
        container: this.root ?? this.panel,
        source: () => ({ emoji: (this.data?.groups ?? []).flatMap((group) => group.emoji), custom: this.options.features.custom ? (this.data?.custom ?? []) : [], data: this.data }),
        tone: () => this.options.tone,
        render: () => ({ mode: this.renderMode, set: this.imageSet }),
        strings: this.strings,
        rtl: () => this.rtl,
        onPick: (detail, base) => {
          if (base && detail.hexcode && this.options.features.recents) {
            this.store.set('recent', recordRecent(this.store.get('recent'), base, detail.hexcode, this.options.maxRecent));
            this.recentStale = true;
          }

          this.announce(detail);
        },
      }));
    }
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
    this.applyTheme();

    if (!inline) {
      this.trigger = el('button', { type: 'button', class: `${PREFIX}-picker-trigger`, 'aria-haspopup': 'dialog', 'aria-expanded': 'false', 'aria-controls': id, 'aria-label': s.open }, this.options.trigger || '🙂');
      this.listen(this.trigger, 'click', () => (this.panel.hidden ? this.open() : this.close()));
      this.root.append(this.trigger);
    }

    // Inline, the picker is part of the page, not a dialog.
    this.panel = el('div', { class: `${PREFIX}-picker-panel`, id, role: inline ? 'group' : 'dialog', 'aria-label': s.open, hidden: !inline });
    this.searchInput = el('input', { type: 'search', class: `${PREFIX}-picker-search`, placeholder: s.search, 'aria-label': s.search, autocomplete: 'off', spellcheck: 'false' });
    const features = this.options.features;

    this.kinds = el('div', { class: `${PREFIX}-picker-kinds`, 'aria-label': s.open, hidden: true });
    this.tabs = el('div', { class: `${PREFIX}-picker-tabs`, role: 'tablist', 'aria-label': s.open, hidden: !features.categoryTabs });
    this.tones = el('div', { class: `${PREFIX}-picker-tones`, role: 'radiogroup', 'aria-label': s.tone, hidden: !features.skinTones });
    this.body = el('div', { class: `${PREFIX}-picker-body` });
    this.status = el('div', { class: `${PREFIX}-picker-status`, role: 'status', 'aria-live': 'polite' }, s.loading);
    this.searchInput.hidden = !features.search;
    // The hovered or focused emoji, large, with its name and shortcode. Decoration for sighted users: each
    // cell already carries its name for assistive tech, so this is hidden from it.
    this.preview = features.preview ? el('div', { class: `${PREFIX}-picker-preview`, 'aria-hidden': 'true' }) : null;

    const footer = el('div', { class: `${PREFIX}-picker-footer` });

    if (this.preview) footer.append(this.preview);

    if (features.setSwitcher) {
      this.switcher = el('select', { class: `${PREFIX}-picker-set`, 'aria-label': s.style ?? DEFAULT_STRINGS.style, hidden: true });
      this.listen(this.switcher, 'change', () => {
        this.store.set('set', this.switcher?.value ?? 'native');
        this.data = this.capped();
        this.memo = null;
        this.byHex = null;
        this.render();
      });
      footer.append(this.switcher);
    }

    footer.append(this.tones);

    if (features.settings) {
      this.gear = el('button', { type: 'button', class: `${PREFIX}-picker-gear`, 'aria-label': s.settings ?? DEFAULT_STRINGS.settings, title: s.settings ?? DEFAULT_STRINGS.settings, 'aria-haspopup': 'dialog', 'aria-expanded': 'false' });
      this.gear.append(icon(GEAR_ICON));
      this.listen(this.gear, 'click', () => (this.closeSettings ? this.closeSettings() : this.openSettings()));
      footer.append(this.gear);
    }

    if (!features.skinTones) {
      this.options.tone = 0;
    }

    this.panel.append(this.searchInput, this.kinds, this.tabs, this.body, footer, this.status);

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
      const cell = (event.target as Element | null)?.closest?.<HTMLElement>(`[${ATTR}-hexcode], [${ATTR}-custom], [${ATTR}-text]`);

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
    this.listen(this.kinds, 'click', (event) => {
      const tab = (event.target as Element | null)?.closest?.<HTMLElement>('[role="tab"]');

      if (tab) {
        this.setKind(tab.getAttribute(`${ATTR}-kind`) as PickerKind);
      }
    });
    if (this.preview) {
      const show = (event: Event): void => this.showPreview((event.target as Element | null)?.closest?.<HTMLElement>('[role="gridcell"]') ?? null);

      this.listen(this.body, 'pointerover', show);
      this.listen(this.body, 'focusin', show);
    }
    // An image that fails to load (a set that lacks it after all, a blocked CDN) shows the device's glyph.
    this.body.addEventListener('error', this.imageFailed, true);
    this.cleanup.push(() => this.body.removeEventListener('error', this.imageFailed, true));

    if (features.perPersonTones) {
      this.cleanup.push(bindToneMenu(this.body, (cell) => {
        const item = this.index().get(cell.getAttribute(`${ATTR}-base`) ?? '');

        return item && Object.keys(item.skins ?? {}).length > 0 ? item : null;
      }, (cell, item) => this.openToneMenu(cell, item)));
    }

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
    this.renderSwitcher();
    this.renderKinds();
    this.renderTabs();
    this.renderTones();
    this.renderBody();
  }

  private sections(): PickerSection[] {
    const features = this.options.features;

    if (this.kind !== 'emoji') {
      this.memo ??= textSections(this.data ?? { groups: [] }, this.kind);

      return this.memo;
    }

    this.memo ??= buildSections(this.data ?? { groups: [] }, {
      categories: this.options.categories,
      sort: this.options.sort,
      recent: features.recents ? readRecent(this.store.get('recent')) : [],
      recentOrder: this.options.recentOrder,
      strings: this.options.strings,
    }).filter((section) => features.custom || !section.custom);

    return this.memo;
  }

  /** The image set switcher: native emoji, then each set the payload offers. Hidden when it offers none. */
  private renderSwitcher(): void {
    const sets = this.raw?.imageSets ?? [];

    if (!this.switcher) return;

    this.switcher.hidden = sets.length === 0;
    const chosen = this.store.get('set');
    const options = [el('option', { value: 'native' }, this.strings.native ?? DEFAULT_STRINGS.native), ...sets.map((set) => el('option', { value: set.set }, set.set.charAt(0).toUpperCase() + set.set.slice(1)))];

    this.switcher.replaceChildren(...options);
    this.switcher.value = typeof chosen === 'string' && sets.some((set) => set.set === chosen) ? chosen : 'native';
  }

  /** The settings menu, beside the gear: theme, clearing recents, and the keyboard shortcuts. */
  openSettings(focusShortcuts = false): void {
    if (!this.gear) return;

    this.closeSettings?.();
    this.gear.setAttribute('aria-expanded', 'true');
    this.closeSettings = openSettingsMenu(this.gear, this.root ?? this.panel, {
      strings: this.strings,
      theme: this.options.theme === 'auto' ? this.themeInEffect : null,
      onTheme: (theme) => {
        this.store.set('theme', theme);
        this.applyTheme();
      },
      recents: readRecent(this.store.get('recent')).length > 0,
      onClearRecents: () => {
        this.store.set('recent', []);
        this.memo = null;
        this.renderWhenShown();
      },
      shortcuts: shortcutList(this.strings, parseShortcut(this.options.shortcut)),
      focusShortcuts,
      rtl: this.rtl,
      onClose: () => {
        this.closeSettings = null;
        this.gear?.setAttribute('aria-expanded', 'false');
      },
    });
  }

  private openToneMenu(cell: HTMLElement, item: PickerEmoji): void {
    this.closeToneMenu?.();
    this.closeToneMenu = openToneMenu(cell, this.root ?? this.panel, item, {
      strings: this.strings,
      tone: this.options.tone,
      mode: this.renderMode,
      set: this.imageSet,
      rtl: this.rtl,
      onPick: (hexcode) => this.choose(item.hexcode, hexcode),
      onClose: () => {
        this.closeToneMenu = null;
      },
    });
  }

  /** The Emoji / Kaomoji / Symbols tabs, shown only when the payload carries more than emoji. */
  private renderKinds(): void {
    const kinds = kindsOf(this.data);
    const s = this.strings;

    if (!kinds.includes(this.kind)) {
      this.kind = 'emoji';
    }

    this.kinds.hidden = kinds.length < 2;

    // With nothing but emoji there is nothing to choose between: no tabs, and no empty tablist to announce.
    if (kinds.length < 2) {
      this.kinds.removeAttribute('role');
      this.kinds.replaceChildren();

      return;
    }

    this.kinds.setAttribute('role', 'tablist');

    this.kinds.replaceChildren(
      ...kinds.map((kind) =>
        el('button', {
          type: 'button',
          role: 'tab',
          class: `${PREFIX}-picker-kind`,
          'aria-selected': String(kind === this.kind),
          tabindex: kind === this.kind ? '0' : '-1',
          [`${ATTR}-kind`]: kind,
        }, s[kind] ?? DEFAULT_STRINGS[kind] ?? kind),
      ),
    );
  }

  private setKind(kind: PickerKind): void {
    if (kind === this.kind) {
      return;
    }

    this.kind = kind;
    this.memo = null;
    this.activeTab = null;
    this.query = '';
    this.searchInput.value = '';
    this.render();
  }

  /** The columns a kind's grid uses: kaomoji are wide, so fewer of them fit a row. */
  private columnsFor(section: PickerSection): number {
    return section.text && this.kind === 'kaomoji' ? Math.max(1, Math.round(this.options.columns / 4)) : this.options.columns;
  }

  private showPreview(cell: HTMLElement | null): void {
    if (!this.preview || !cell) {
      return;
    }

    const glyph = el('span', { class: `${PREFIX}-picker-preview-glyph` });
    const image = cell.querySelector('img');
    const base = cell.getAttribute(`${ATTR}-base`);
    const custom = cell.getAttribute(`${ATTR}-custom`);
    const shortcode = custom !== null ? customCode(custom, this.data) : base !== null ? this.index().get(base)?.shortcode : null;

    if (image) {
      glyph.append(el('img', { src: image.getAttribute('src') ?? '', alt: '', class: `${PREFIX} ${PREFIX}-image` }));
    } else {
      glyph.textContent = cell.textContent ?? '';
    }

    const words = el('span', { class: `${PREFIX}-picker-preview-text` });
    words.append(el('span', { class: `${PREFIX}-picker-preview-name` }, cell.getAttribute('aria-label') ?? ''));

    if (shortcode) {
      words.append(el('span', { class: `${PREFIX}-picker-preview-code` }, custom !== null ? shortcode : customCode(shortcode, this.data)));
    }

    this.preview.replaceChildren(glyph, words);
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
        const selected = section.slug === active;
        const tab = el('button', {
          type: 'button',
          role: 'tab',
          class: `${PREFIX}-picker-tab`,
          title: section.label,
          'aria-label': section.label,
          'aria-selected': String(selected),
          'aria-controls': this.sectionId(section.slug),
          tabindex: selected ? '0' : '-1',
          [`${ATTR}-section`]: section.slug,
        });

        tab.append(tabFace(section));

        return tab;
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

    this.markTab(slug);
    this.flushBody();

    const section = this.body.querySelector<HTMLElement>(`section[${ATTR}-section="${slug}"]`);

    if (section) {
      this.body.scrollTop = section.offsetTop - this.body.offsetTop;
    }
  }

  /** Marks a category tab as the current one, keeping it in view in a tab bar that scrolls sideways. */
  private markTab(slug: string): void {
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

  /**
   * Draws the grid. A search draws its results (at most 200) at once. Browsing draws the first sections,
   * about a screenful, before the browser paints, and the rest in idle time, so opening the picker does not
   * wait for ~1,900 buttons; anything that needs a section not drawn yet (a tab, a key) finishes the job first.
   */
  private renderBody(): void {
    const s = this.strings;
    const term = this.query.trim();
    const sections: PickerSection[] = (term ? searchResults(this.sections(), term, s) : this.sections()).filter((section) => section.items.length > 0);
    const token = ++this.bodyToken;
    const nodes: HTMLElement[] = [];
    let drawn = 0;

    while (sections.length > 0 && (term !== '' || drawn < FIRST_PAINT_CELLS)) {
      const section = sections.shift() as PickerSection;

      nodes.push(this.buildSection(section));
      drawn += section.items.length;
    }

    this.body.replaceChildren(...nodes);
    this.status.textContent = term ? resultText(s, drawn) : '';
    this.rest = sections;
    this.stopSpy?.();
    this.stopSpy = null;
    // The tab follows the scroll: whichever section is at the top of the body is the current one.
    this.finishBody = (): void => {
      this.finishBody = null;
      this.stopSpy = term ? null : spySections(this.body, (slug) => this.markTab(slug));
    };

    if (this.rest.length === 0) {
      this.finishBody();
    } else {
      const step = (): void => {
        if (token !== this.bodyToken) return;

        const next = this.rest.shift();

        if (next) this.body.append(this.buildSection(next));

        if (this.rest.length > 0) whenIdle(step);
        else this.finishBody?.();
      };

      whenIdle(step);
    }

    const first = this.body.querySelector<HTMLElement>('[role="gridcell"]');

    this.active = null;

    if (first) {
      first.tabIndex = 0;
      this.active = first;
    }
  }

  /** Draws every section still waiting for idle time, now. */
  flushBody(): void {
    if (this.rest.length === 0) return;

    this.body.append(...this.rest.splice(0).map((section) => this.buildSection(section)));
    this.finishBody?.();
  }

  private buildSection(section: PickerSection): HTMLElement {
    const heading = el('div', { class: `${PREFIX}-picker-heading`, id: `${this.panel.id}-${section.slug}` }, section.label);
    const grid = el('div', { class: `${PREFIX}-picker-grid`, role: 'grid', 'aria-labelledby': heading.id });
    const columns = this.columnsFor(section);
    let row: HTMLDivElement | null = null;

    if (section.text) {
      grid.classList.add(`${PREFIX}-picker-grid-text`);
      grid.style.setProperty(`--${PREFIX}-picker-columns`, String(columns));
    }

    section.items.forEach((item, index) => {
      if (index % columns === 0 || row === null) {
        row = el('div', { role: 'row', class: `${PREFIX}-picker-row` });
        grid.append(row);
      }

      row.append(section.text ? this.textCell(item as PickerText) : section.custom ? this.customCell(item as PickerCustom) : this.cell(item as PickerEmoji));
    });

    const block = el('section', { class: `${PREFIX}-picker-section`, id: this.sectionId(section.slug), [`${ATTR}-section`]: section.slug });

    block.append(heading, grid);

    return block;
  }

  private cell(item: PickerEmoji): HTMLButtonElement {
    const hexcode = item.pick ?? withTone(item, this.options.tone);
    const url = drawsImage(item, hexcode, this.renderMode, this.imageSet);
    const button = el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell`, title: item.name, 'aria-label': item.name, [`${ATTR}-hexcode`]: hexcode, [`${ATTR}-base`]: item.hexcode }, url ? undefined : charOf(hexcode));

    if (url) {
      // The cell is named; the image is decoration. A failed image falls back to the glyph (see buildShell).
      button.append(el('img', { src: url, alt: '', class: `${PREFIX} ${PREFIX}-image`, draggable: 'false', loading: 'lazy', decoding: 'async' }));
    }

    return button;
  }

  private textCell(item: PickerText): HTMLButtonElement {
    return el('button', { type: 'button', role: 'gridcell', tabindex: '-1', class: `${PREFIX}-picker-cell ${PREFIX}-picker-cell-text`, title: item.name, 'aria-label': item.name, [`${ATTR}-text`]: item.text }, item.text);
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

    // Picker shortcuts: / to search, Alt+1–9 for a category, ? for the shortcut list. Not while typing.
    const typing = target === this.searchInput;

    if (!typing && event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey) {
      event.preventDefault();
      this.searchInput.focus();

      return;
    }

    if (event.altKey && !event.ctrlKey && !event.metaKey && /^Digit[1-9]$/.test(event.code)) {
      const tabs = [...this.tabs.querySelectorAll<HTMLElement>('[role="tab"]')];
      const wanted = tabs[Number(event.code.slice(5)) - 1];

      if (wanted) {
        event.preventDefault();
        this.showSection(wanted.getAttribute(`${ATTR}-section`) ?? '');
        wanted.focus();
      }

      return;
    }

    if (!typing && event.key === '?' && this.gear) {
      event.preventDefault();
      this.openSettings(true);

      return;
    }

    const tab = target?.closest?.<HTMLElement>('[role="tab"]');
    const radio = target?.closest?.<HTMLElement>('[role="radio"]');

    if (tab && this.kinds.contains(tab)) {
      const items = [...this.kinds.querySelectorAll<HTMLElement>('[role="tab"]')];
      const next = rovingIndex(items.length, items.indexOf(tab), event.key, this.rtl);

      if (next !== null) {
        event.preventDefault();
        this.setKind(items[next]?.getAttribute(`${ATTR}-kind`) as PickerKind);
        this.kinds.querySelector<HTMLElement>('[aria-selected="true"]')?.focus();
      }

      return;
    }

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

    // Keys that can reach past what is drawn (End, PageDown, the last row) need every section.
    this.flushBody();

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
    const text = cell.getAttribute(`${ATTR}-text`);
    let detail: SelectDetail;

    if (text !== null) {
      detail = { emoji: text, hexcode: null, name: cell.getAttribute('aria-label') ?? text, shortcode: null, custom: false, kind: this.kind };
    } else if (name !== null) {
      const custom = (this.data?.custom ?? []).find((c) => c.name === name);
      detail = { emoji: customCode(name, this.data), hexcode: null, name: custom?.label ?? name, shortcode: name, custom: true };
    } else {
      const hexcode = cell.getAttribute(`${ATTR}-hexcode`) ?? '';

      this.choose(cell.getAttribute(`${ATTR}-base`) ?? hexcode, hexcode);

      return;
    }

    this.deliver(detail);
  }

  /** Picks one form of an emoji (from its cell, or from the tone menu): recorded as recent, then delivered. */
  private choose(base: string, hexcode: string): void {
    const item = this.index().get(base);

    if (this.options.features.recents) {
      this.store.set('recent', recordRecent(this.store.get('recent'), base, hexcode, this.options.maxRecent));
      // Redrawn once nothing is under the pointer, so a quick second click never lands on a moved cell.
      this.recentStale = true;
    }

    this.deliver({ emoji: charOf(hexcode), hexcode, name: item?.name ?? '', shortcode: item?.shortcode ?? null, custom: false });
  }

  /** Tells listeners about a pick: the bubbling DOM event, then the on('select') handlers. */
  private announce(detail: SelectDetail): void {
    this.element.dispatchEvent(new CustomEvent(EVENT, { detail, bubbles: true }));
    this.emit('select', detail);
  }

  /** Inserts a pick, announces it, and closes the popover when it should. */
  private deliver(detail: SelectDetail): void {
    const target = this.options.target;

    if (target) {
      insertText(target, detail.emoji, this.caretKnown);
    }

    this.announce(detail);

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
