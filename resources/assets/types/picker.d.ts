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
}
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
 * detectMaxVersion() once per page, after web fonts have loaded: a page whose emoji font is a web font
 * (Noto Color Emoji from Google Fonts) would otherwise be measured before the font arrives.
 */
export declare function detectMaxVersionOnce(): Promise<string | null>;
/**
 * The payload without emoji newer than `cap` ('auto' asks the browser; null or '' keeps everything). Toned
 * forms are capped on their own version, since they can be newer than their base (🤝 is 3.0, its tones
 * 14.0): a capped tone is dropped from `skins`, and the emoji then falls back to its untoned form.
 */
export declare function capPayload(data: PickerPayload, cap: string | null | undefined, detect?: () => string | null): PickerPayload;
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
    constructor(payload: PickerPayload | string);
    load(): Promise<PickerPayload>;
}
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
    private reload;
    private refresh;
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
    private renderBody;
    private cell;
    private textCell;
    private customCell;
    private onKey;
    private select;
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
