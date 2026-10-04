// GENERATED from resources/assets/scripts/picker.ts by .dev/tools/types.mjs — do not edit.
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
    items: Array<{
        text?: string;
        char?: string;
        name: string;
    }>;
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
    ready: {
        picker: Picker;
    };
    error: {
        error: unknown;
    };
}
/** One rendered block: Frequently used, a Unicode group, the custom emoji, or a group of kaomoji or symbols. */
export type PickerSection = {
    slug: string;
    label: string;
    custom?: false;
    text?: false;
    items: PickerEmoji[];
}
/** Frequently used, when it holds custom emoji beside Unicode ones. */
 | {
    slug: string;
    label: string;
    custom?: false;
    text?: false;
    mixed: true;
    items: Array<PickerEmoji | PickerCustom>;
} | {
    slug: string;
    label: string;
    custom: true;
    text?: false;
    items: PickerCustom[];
} | {
    slug: string;
    label: string;
    custom?: false;
    text: true;
    items: PickerText[];
};
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
declare const MOUNTED: unique symbol;
type Mountable = HTMLElement & {
    [MOUNTED]?: Picker;
};
/** The interface strings in English; every one can be replaced through the `strings` option. */
export declare const DEFAULT_STRINGS: PickerStrings;
/** Every feature on. */
export declare const DEFAULT_FEATURES: PickerFeatures;
/** Features from anywhere (an attribute's JSON, a prop): only booleans count, everything else stays on. */
export declare function readFeatures(value: unknown): PickerFeatures;
/**
 * Outline icons for the category tabs, as SVG path data on a 24×24 grid, drawn with currentColor so they
 * follow the theme. Paths, not markup: both pickers build the <svg> themselves, so nothing is parsed.
 */
export declare const TAB_ICONS: Readonly<Record<string, readonly string[]>>;
/** The groups of one kind as sections. Kaomoji and symbols are text, inserted as they are. */
export declare function textSections(data: PickerPayload, kind: Exclude<PickerKind, 'emoji'>): Array<{
    slug: string;
    label: string;
    text: true;
    items: PickerText[];
}>;
/** The kinds a payload can show, in tab order: emoji always, then kaomoji and symbols when it carries them. */
export declare function kindsOf(data: PickerPayload | null): PickerKind[];
/** Text items whose name or text matches every word of a term. */
export declare function searchText(items: PickerText[], term: string, limit?: number): PickerText[];
/**
 * Marks the section scrolled to as the current tab: the topmost section still showing in the top third of
 * the scrolling body. Returns the function that stops watching. Without IntersectionObserver it does nothing.
 */
export declare function spySections(body: HTMLElement, onActive: (slug: string) => void): () => void;
/** The hand shown for each tone choice, 0 (default) to 5. */
export declare const TONE_SWATCHES: readonly string[];
/** Lower-case and strip diacritics, so "fusee" finds "fusée" and "CAFE" finds "café". */
export declare function fold(text: string): string;
/** "1F44B-1F3FD" → "👋🏽". */
export declare function charOf(hexcode: string): string;
/**
 * The hexcode an emoji takes with a skin tone (1–5), or its own when it takes none. An emoji whose base the
 * policy refuses (`base: false`) falls back to its first permitted toned form, never to the base.
 */
export declare function withTone(item: PickerEmoji, tone: number): string;
/** The text a custom emoji is inserted as: its name between the payload's shortcode delimiters. */
export declare function customCode(name: string, data?: Pick<PickerPayload, 'delimiters'> | null): string;
/**
 * The filename rules of the rule-based sets, mirroring the server's Filenames class: Twemoji drops FE0F
 * unless the sequence has a ZWJ, Noto always drops it and pads, OpenMoji keeps the hexcode (dropping a lone
 * trailing FE0F), JoyPixels drops every FE0F. Pure, so the same emoji gets the same URL on both sides.
 */
export declare const IMAGE_RULES: Readonly<Record<NonNullable<PickerImageSet['rule']>, (hexcode: string) => string>>;
/** The URL of an emoji's image in a set, or null when the set has none for it. */
export declare function imageUrl(set: PickerImageSet | null | undefined, hexcode: string): string | null;
/**
 * Whether a cell draws an image rather than the device's glyph: always in 'image' mode, never in 'native',
 * and in 'auto' only for what capPayload marked as beyond the device (a newer emoji, a newer toned form, a
 * flag where the OS draws letters). Only when the set has the image; otherwise the glyph is kept.
 */
export declare function drawsImage(item: PickerEmoji, hexcode: string, mode: RenderMode, set: PickerImageSet | null | undefined): string | null;
/** Compares two dotted Emoji versions ("15.1" > "15.0"). */
export declare function byVersion(a: string, b: string): number;
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
export declare function detectMaxVersion(doc?: Pick<Document, 'createElement'> | undefined): string | null;
/**
 * Whether this browser draws flags (🇺🇸) as flags. Windows draws regional indicators as letters, in black, so
 * a flag comes out in colour only where the OS has flag glyphs. Null when it cannot tell.
 */
export declare function detectFlags(doc?: Pick<Document, 'createElement'> | undefined): boolean | null;
/** detectFlags() once per page, after web fonts have loaded. */
export declare function detectFlagsOnce(): Promise<boolean | null>;
/**
 * detectMaxVersion() once per page, after web fonts have loaded: a page whose emoji font is a web font
 * (Noto Color Emoji from Google Fonts) would otherwise be measured before the font arrives.
 */
export declare function detectMaxVersionOnce(): Promise<string | null>;
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
export declare function capPayload(data: PickerPayload, cap: string | null | undefined, detect?: () => string | null, fallback?: {
    set?: PickerImageSet | null;
    flags?: boolean | null;
}): PickerPayload;
/**
 * Ranks emoji for a search term: exact name, name prefix, shortcode, keyword prefix, anywhere. Every word
 * of the term must match somewhere.
 */
export declare function search(items: PickerEmoji[], term: string, limit?: number): PickerEmoji[];
/** "No emoji found", "1 result", "12 results". */
export declare function resultText(strings: Pick<PickerStrings, 'noResults' | 'results' | 'resultsOne'>, count: number): string;
/** The custom emoji whose name or label matches every word of a term, name prefix first. */
export declare function searchCustom(items: PickerCustom[], term: string): PickerCustom[];
/** A tone from anywhere (an attribute, storage, a prop): 0–5, and 0 for anything else. */
export declare function clampTone(value: unknown): number;
/** A column count: a whole number from 1 to 24, else the fallback. */
export declare function clampColumns(value: unknown, fallback?: number): number;
/**
 * Recents as read back from storage: only well-formed entries. A corrupt value or one an older or newer
 * version wrote reads as no recents, instead of breaking the picker until storage is cleared.
 */
export declare function readRecent(value: unknown): RecentEntry[];
/** Orders a list: "default" keeps CLDR order, "name" sorts by name, "newest" puts the latest Emoji version first. */
export declare function sortItems(items: PickerEmoji[], mode: SortMode): PickerEmoji[];
/**
 * Records a pick in the recent list: newest first, once per base emoji (a toned 👋🏽 replaces 👋), at most
 * `max` entries. Returns a new list.
 */
export declare function recordRecent(stored: unknown, base: string, hexcode: string, max: number, now?: number): RecentEntry[];
/** Orders recents: "recent" by time of last use, "frequent" by count then time. */
export declare function orderRecent(list: RecentEntry[], mode: RecentOrder): RecentEntry[];
/** How a custom emoji is recorded among recents, so it cannot collide with a hexcode. */
export declare const CUSTOM_RECENT = "custom:";
export declare function customRecentKey(name: string): string;
/** Every emoji of the payload by its base hexcode. */
export declare function indexPayload(data: PickerPayload): Map<string, PickerEmoji>;
/**
 * What the picker shows when nothing is searched: Frequently used, then each group, then Custom — limited to
 * `categories` when given. Recents whose emoji the payload no longer has (a capped version) drop out.
 */
export declare function buildSections(data: PickerPayload, options?: {
    categories?: string[];
    sort?: SortMode;
    recent?: RecentEntry[];
    recentOrder?: RecentOrder;
    strings?: Partial<PickerStrings>;
    custom?: boolean;
}): PickerSection[];
/** The search results over a list of sections: the emoji of every non-custom group, ranked. */
export declare function searchSections(sections: PickerSection[], term: string, limit?: number): PickerEmoji[];
/** The most results a search draws; a one-letter term would otherwise draw nearly every emoji. */
export declare const MAX_RESULTS = 200;
/**
 * What a search shows: the ranked emoji, then the matching custom emoji, as sections. Shared by both
 * pickers so a term finds the same things in each.
 */
export declare function searchResults(sections: PickerSection[], term: string, strings: Pick<PickerStrings, 'search' | 'custom'>, limit?: number): PickerSection[];
/**
 * The cell a grid key moves focus to, read from the rendered rows so both pickers share it: arrows keep
 * the column across rows and sections (clamped to a shorter row), Left and Right swap under RTL,
 * PageUp/PageDown jump a section, Home/End go to the first and last cell. 'search' means ArrowUp left the
 * first row. null means the key is not a grid key, or there is nowhere to go.
 */
export declare function gridTarget(body: ParentNode, cell: Element, key: string, rtl?: boolean): HTMLElement | 'search' | null;
/**
 * The index a roving-tabindex key moves to in a row of tabs or radios: arrows wrap, Left and Right swap
 * under RTL, Home and End go to the ends. null for any other key.
 */
export declare function rovingIndex(count: number, current: number, key: string, rtl?: boolean): number | null;
/**
 * Inserts text into an input, a textarea or a contenteditable element, and dispatches `input` and
 * `change`, so frameworks see it (wire:model.change and x-model.lazy listen for change). In a field at the
 * caret when `caretKnown` (the field has had focus); otherwise at the end, because a field nobody has
 * focused reports its caret at 0 and the text would land before what is already there. Returns false, and
 * changes nothing, when the text would take the field past its maxlength.
 */
export declare function insertText(target: Insertable, text: string, caretKnown: boolean): boolean;
/** Reads every data-laranail-emoji-* option on an element, typed. Unknown attributes are ignored. */
export declare function parseOptions(element: Element): ParsedOptions;
/** A render mode from an attribute or prop; anything unknown is 'auto'. */
export declare function parseRender(value: unknown): RenderMode;
/** A placement from an attribute or prop; anything unknown is 'auto'. */
export declare function parsePlacement(value: unknown): Placement;
/** Loads the payload from the package's API (`…/api/v1`) or straight from a `…/picker` URL. */
export declare class ApiSource implements PickerSource {
    private readonly init;
    readonly url: string;
    constructor(url: string, init?: RequestInit);
    /**
     * One request per URL and locale for every picker on the page: a thread with a reply picker per message
     * fetches the payload once instead of once each (and does not spend the API's rate limit doing it).
     */
    private static inflight;
    load(locale?: string | null, signal?: AbortSignal): Promise<PickerPayload>;
    /** Forgets every shared request, so the next load fetches again (after a locale's data changed, or in tests). */
    static clear(): void;
    private fetch;
}
/** A payload already in hand: an object, or the id of a <script type="application/json"> holding one. */
export declare class StaticSource implements PickerSource {
    private readonly payload;
    /**
     * Each data block parsed once, however many pickers read it: ten pickers on a page share one ~400 KB
     * JSON.parse. Keyed by the element, so a block a morph replaces is read afresh.
     */
    private static parsed;
    constructor(payload: PickerPayload | string);
    load(): Promise<PickerPayload>;
}
/** How many cells a grid draws before the browser first paints; the rest follow in idle time. */
export declare const FIRST_PAINT_CELLS = 200;
/** A store that forgets on reload. */
export declare function memoryStore(): PickerStore;
/**
 * localStorage, namespaced, and never fatal: private mode, a full quota or blocked storage fall back to
 * memory. Only hexcodes, counts and times are written — never text the user typed.
 */
export declare function localStorageStore(namespace?: string): PickerStore;
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
/**
 * Where to put a floating box next to a reference, the way Popper and Floating UI do it, in one pure
 * function: offset, then flip to the opposite side when the preferred one is too small, then shift along the
 * cross axis to stay on screen, then size to the room left, then place the arrow at the reference's centre,
 * clamped clear of the corners, and hide when the reference has scrolled away. Pure, so it is tested without
 * a browser; Popover applies it.
 */
export declare function computePosition(reference: Rect, floating: {
    width: number;
    height: number;
}, viewport: Rect, options?: PositionOptions): Position;
/**
 * Calls `update` whenever either element could have moved: either one resizing, any scroll (captured, so a
 * scrolling container counts too), a window resize, or the visual viewport changing (pinch zoom, the
 * on-screen keyboard). Batched to one call per frame. Returns the function that stops it.
 */
export declare function autoUpdate(reference: Element, floating: Element, update: () => void): () => void;
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
export declare class Popover {
    private readonly root;
    private readonly trigger;
    private readonly panel;
    private readonly arrow;
    private readonly backdrop;
    private readonly handle;
    private readonly options;
    private stop;
    private undoSheet;
    private sheet;
    constructor(root: HTMLElement, trigger: HTMLElement, panel: HTMLElement, arrow: HTMLElement | null, backdrop: HTMLElement | null, handle: HTMLElement | null, options?: PopoverOptions);
    /** Whether the panel is a bottom sheet (decided on each open, from the viewport's width). */
    get isSheet(): boolean;
    open(): void;
    close(): void;
    destroy(): void;
    /** Positions the panel now; autoUpdate() calls this on every move. */
    place(): void;
    private showTopLayer;
    private openSheet;
    /** Drag the handle down past a fifth of the sheet to dismiss it, up to expand it to full height. */
    private dragHandle;
    /** Expands the sheet to full height (search focus does, so results are not hidden behind the keyboard). */
    expand(): void;
}
/**
 * The toned forms an emoji offers. One person: tone 0 (none) to 5. Two people (🤝, couples): a tone for each,
 * 1 to 5, where the same tone on both is keyed by the one tone ("3") and different tones by the pair
 * ("3-5"). `form()` answers the hexcode for a choice, or null when the payload does not offer it (the policy
 * or the version cap removed it).
 */
export declare function toneForms(item: PickerEmoji): {
    people: 0 | 1 | 2;
    form: (first: number, second?: number) => string | null;
};
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
export declare function openAnchored(menu: HTMLElement, options: AnchoredOptions): () => void;
/**
 * A small popover beside an emoji for choosing its tone for this one pick, as phones do on a long press:
 * six toned forms for one person, or a tone for each person in 🤝 and couples with the result previewed.
 * It is placed with computePosition() and carries the same caret as the picker. Arrow keys move, Enter or
 * Space picks, Escape closes and returns focus to the emoji. Returns the function that closes it.
 */
export declare function openToneMenu(anchor: HTMLElement, container: HTMLElement, item: PickerEmoji, options: ToneMenuOptions): () => void;
/**
 * Opens the tone menu for an emoji cell on a right click, the context-menu key or Shift+F10, or a long press
 * (half a second without moving), which is how phones offer tones. Delegated on the grid, so both pickers
 * bind it once. `resolve` answers the emoji a cell shows, or null for one with no tones (then the browser's
 * own context menu is left alone). Returns the function that unbinds it.
 */
export declare function bindToneMenu(body: HTMLElement, resolve: (cell: HTMLElement) => PickerEmoji | null, open: (cell: HTMLElement, item: PickerEmoji) => void): () => void;
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
export declare const DEFAULT_SHORTCUT = "Mod+Shift+.";
/** Whether the platform's primary modifier is ⌘. */
export declare function isApple(nav?: {
    platform?: string;
    userAgent?: string;
} | undefined): boolean;
/**
 * Parses "Mod+Shift+.", "Ctrl+Alt+E", "Alt+1". Mod is ⌘ on Apple platforms and Ctrl elsewhere. Null for an
 * empty or unreadable string, which turns the shortcut off.
 */
export declare function parseShortcut(text: string | null | undefined): Shortcut | null;
/** Whether a key event is the shortcut, modifiers exactly. */
export declare function matchesShortcut(event: Pick<KeyboardEvent, 'code' | 'ctrlKey' | 'metaKey' | 'altKey' | 'shiftKey'>, shortcut: Shortcut, apple?: boolean): boolean;
/** How a shortcut is written for the user: ⌘⇧. on Apple platforms, Ctrl+Shift+. elsewhere. */
export declare function shortcutLabel(shortcut: Shortcut, apple?: boolean): string;
/** The keys a picker answers to, for the shortcut list in its settings: [what it does, the keys]. */
export declare function shortcutList(strings: PickerStrings, open: Shortcut | null, apple?: boolean): Array<[string, string]>;
/** Opens a picker when its shortcut is pressed in a field. Returns the function that unbinds it. */
export declare function bindShortcut(field: EventTarget, shortcut: Shortcut | null, open: () => void): () => void;
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
export declare function openSettingsMenu(anchor: HTMLElement, container: HTMLElement, options: SettingsMenuOptions): () => void;
/**
 * Where the text caret is in an input or a textarea, in viewport coordinates: the box a mirror of the field
 * puts after the text before the caret. The mirror copies every style that moves text, so wrapping, padding
 * and scrolling come out the same.
 */
export declare function caretRect(field: HTMLInputElement | HTMLTextAreaElement): Rect;
export interface AutocompleteOptions {
    /** Where the suggestion list lives in the DOM (the picker's root). */
    container: HTMLElement;
    /** The emoji and custom emoji to suggest from, as the picker has them now. */
    source: () => {
        emoji: PickerEmoji[];
        custom: PickerCustom[];
        data: PickerPayload | null;
    };
    /** How a suggestion is drawn and what it inserts: the picker's tone, render mode and image set. */
    tone: () => number;
    render: () => {
        mode: RenderMode;
        set: PickerImageSet | null;
    };
    strings: PickerStrings;
    /** Letters after the colon before suggestions appear (default 2). */
    min?: number;
    /** Suggestions shown at most (default 8). */
    limit?: number;
    rtl?: () => boolean;
    /** Called with what was inserted, after the field has it, and its recents key (the base hexcode, or custom:name). */
    onPick: (detail: SelectDetail, base: string | null) => void;
}
/**
 * Suggests emoji as the user types a shortcode, the way Slack and Discord do: ":smi" in the field opens a list
 * beside the text caret (with the picker's caret pointing at it). Arrow keys move, Enter or Tab inserts the
 * emoji in place of the code, Escape dismisses until the next code. The field keeps focus throughout, and
 * gets aria-autocomplete, aria-expanded, aria-controls and aria-activedescendant while the list is open.
 * Inputs and textareas only. Returns the function that detaches it.
 */
export declare function attachAutocomplete(field: HTMLInputElement | HTMLTextAreaElement, options: AutocompleteOptions): () => void;
/** The settings button's outline gear, on the same 24×24 grid as the tab icons. */
export declare const GEAR_ICON: readonly string[];
/** An outline icon from SVG path data, built with createElementNS so it stays CSP-safe. */
export declare function icon(paths: readonly string[], size?: number): SVGSVGElement;
/**
 * What a category tab shows: the group's outline icon when there is one, else its first emoji, else a short
 * label (kaomoji and symbol groups). Built with createElementNS, so it stays CSP-safe.
 */
export declare function tabFace(section: PickerSection): Node;
type ResolvedOptions = Required<Omit<PickerOptions, 'source' | 'target' | 'store' | 'features'>> & Pick<PickerOptions, 'source' | 'target' | 'store'> & {
    features: PickerFeatures;
};
export declare class Picker {
    /**
     * Starts a picker on an element; chain the options, then mount(). An element that already has a live
     * picker (auto-initialised, say) returns that picker, so its handlers and methods reach the one on screen.
     */
    static create(element: HTMLElement, options?: PickerOptions): Picker;
    readonly element: Mountable;
    options: ResolvedOptions;
    data: PickerPayload | null;
    query: string;
    active: HTMLElement | null;
    root?: HTMLDivElement;
    trigger?: HTMLButtonElement;
    panel: HTMLDivElement;
    searchInput: HTMLInputElement;
    private tabs;
    private kinds;
    private preview;
    private tones;
    private body;
    private status;
    private handlers;
    private cleanup;
    private targetCleanup;
    private caretKnown;
    private byHex;
    private memo;
    private recentStale;
    private activeTab;
    private loads;
    private abort;
    private searchTimer;
    private popover;
    private kind;
    /** The payload as loaded, before the device's cap: what a change of image set re-caps from. */
    private raw;
    private support;
    private closeToneMenu;
    private switcher;
    private gear;
    private closeSettings;
    /** A popover's grid is built the first time it opens, not on page load. */
    private pendingRender;
    /** The sections still to draw in idle time, and which render they belong to. */
    private rest;
    private bodyToken;
    private finishBody;
    private readonly imageFailed;
    private stopSpy;
    constructor(element: HTMLElement, options?: PickerOptions);
    source(source: PickerSource): this;
    locale(locale: string | null): this;
    skinTone(tone: number): this;
    sort(mode: SortMode): this;
    categories(list: string[]): this;
    columns(count: number): this;
    /** Hide emoji newer than this Emoji version: 'auto' (default) asks the browser, null shows everything. */
    maxVersion(version: string | null): this;
    closeOnSelect(close?: boolean): this;
    /** Fixes the colour scheme ('light', 'dark'), or follows the OS and the page again ('auto'). */
    theme(theme: PickerTheme): this;
    /**
     * The theme in effect: one the page fixed (the option) wins; otherwise the one the user chose in the
     * settings menu, remembered per user-key; otherwise 'auto'.
     */
    private get themeInEffect();
    private applyTheme;
    inline(inline?: boolean): this;
    target(target: string | Insertable | null): this;
    history({ max, store, order }?: {
        max?: number;
        store?: PickerStore;
        order?: RecentOrder;
    }): this;
    on<K extends keyof PickerEvents>(event: K, handler: (detail: PickerEvents[K]) => void): this;
    get strings(): PickerStrings;
    get store(): PickerStore;
    /** Builds the UI and loads the payload. Resolves once the emoji are drawn. */
    mount(): Promise<this>;
    /** Whether the picker's UI is still inside its element (a morph can strip it out). */
    isAttached(): boolean;
    destroy(): void;
    open(): void;
    /**
     * Closes the popover. Focus goes back to the trigger by default; `'target'` sends it to the field a pick
     * was inserted into, so typing carries on; `'none'` leaves it where it went (an outside click).
     */
    close(focus?: 'trigger' | 'target' | 'none'): void;
    focusCell(cell: HTMLElement | null | undefined): void;
    private load;
    /** The image set in use: the one chosen in the set switcher, else the payload's. Null when there is none. */
    private get imageSet();
    /** How cells draw: the option, unless the set switcher picked a set (then every emoji from it). */
    private get renderMode();
    /** The payload capped for this device, falling back to images where the mode allows. */
    private capped;
    private reload;
    private refresh;
    /** Draws now when the picker is visible; a closed popover is drawn when it next opens. */
    private renderWhenShown;
    /** Redraws Frequently used after picks made while the panel was open, now that nothing is under the pointer. */
    private refreshRecents;
    private listen;
    private emit;
    /**
     * A field nobody has focused reports its caret at 0, so the first pick would land before text already in
     * it. The caret is trusted once the field has had focus; until then, picks append. Re-targeting drops the
     * previous field's listener.
     */
    private watchTarget;
    private get rtl();
    private buildShell;
    /** On the phone sheet, a horizontal swipe across the emoji moves to the next or previous category. */
    private swipeSections;
    private scheduleSearch;
    private render;
    private sections;
    /** The image set switcher: native emoji, then each set the payload offers. Hidden when it offers none. */
    private renderSwitcher;
    /** The settings menu, beside the gear: theme, clearing recents, and the keyboard shortcuts. */
    openSettings(focusShortcuts?: boolean): void;
    private openToneMenu;
    /** The Emoji / Kaomoji / Symbols tabs, shown only when the payload carries more than emoji. */
    private renderKinds;
    private setKind;
    /** The columns a kind's grid uses: kaomoji are wide, so fewer of them fit a row. */
    private columnsFor;
    private showPreview;
    private index;
    private sectionId;
    private renderTabs;
    /** Clears any search, marks the tab, and scrolls the body — never the page — to a section. */
    private showSection;
    /** Marks a category tab as the current one, keeping it in view in a tab bar that scrolls sideways. */
    private markTab;
    private renderTones;
    /** Applies a tone, updating the radios in place so the one the user is on keeps focus. */
    private setTone;
    /**
     * Draws the grid. A search draws its results (at most 200) at once. Browsing draws the first sections,
     * about a screenful, before the browser paints, and the rest in idle time, so opening the picker does not
     * wait for ~1,900 buttons; anything that needs a section not drawn yet (a tab, a key) finishes the job first.
     */
    private renderBody;
    /** Draws every section still waiting for idle time, now. */
    flushBody(): void;
    private buildSection;
    private cell;
    private textCell;
    private customCell;
    private onKey;
    private select;
    /** Picks one form of an emoji (from its cell, or from the tone menu): recorded as recent, then delivered. */
    private choose;
    /** Tells listeners about a pick: the bubbling DOM event, then the on('select') handlers. */
    private announce;
    /** Inserts a pick, announces it, and closes the popover when it should. */
    private deliver;
}
/**
 * Mounts a picker from its data-laranail-emoji-* attributes. Idempotent: a mounted element is left alone,
 * unless something (a Livewire or Turbo morph) stripped the picker out of it, when it is mounted afresh.
 */
export declare function mountElement(element: HTMLElement): Picker;
/**
 * Mounts every [data-laranail-emoji-picker] under root now and as they are added later, with one
 * MutationObserver. Returns a function that stops observing.
 */
export declare function autoInit(root?: Document | Element): () => void;
export {};
