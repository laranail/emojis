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
}
export interface PickerSource {
    load(locale?: string | null): Promise<PickerPayload>;
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
    target?: HTMLInputElement | HTMLTextAreaElement | null;
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
/** One rendered block: Frequently used, a Unicode group, or the custom emoji. */
export type PickerSection = {
    slug: string;
    label: string;
    custom?: false;
    items: PickerEmoji[];
} | {
    slug: string;
    label: string;
    custom: true;
    items: PickerCustom[];
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
}
type Insertable = HTMLInputElement | HTMLTextAreaElement;
declare const MOUNTED: unique symbol;
type Mountable = HTMLElement & {
    [MOUNTED]?: Picker;
};
/** The interface strings in English; every one can be replaced through the `strings` option. */
export declare const DEFAULT_STRINGS: PickerStrings;
/** The hand shown for each tone choice, 0 (default) to 5. */
export declare const TONE_SWATCHES: readonly string[];
/** Lower-case and strip diacritics, so "fusee" finds "fusée" and "CAFE" finds "café". */
export declare function fold(text: string): string;
/** "1F44B-1F3FD" → "👋🏽". */
export declare function charOf(hexcode: string): string;
/** The hexcode an emoji takes with a skin tone (1–5), or its own when it takes none. */
export declare function withTone(item: PickerEmoji, tone: number): string;
/** Compares two dotted Emoji versions ("15.1" > "15.0"). */
export declare function byVersion(a: string, b: string): number;
/**
 * The newest Emoji version this browser draws, or null when it cannot tell (no canvas, as in tests and
 * old browsers): then nothing is hidden. An emoji the font lacks draws exactly like an unassigned code point
 * (the "tofu" box), and a sequence it lacks draws as its parts, wider than one emoji.
 */
export declare function detectMaxVersion(doc?: Pick<Document, 'createElement'> | undefined): string | null;
/** The payload without emoji newer than `cap` ('auto' asks the browser; null or '' keeps everything). */
export declare function capPayload(data: PickerPayload, cap: string | null | undefined, detect?: () => string | null): PickerPayload;
/**
 * Ranks emoji for a search term: exact name, name prefix, shortcode, keyword prefix, anywhere. Every word
 * of the term must match somewhere.
 */
export declare function search(items: PickerEmoji[], term: string): PickerEmoji[];
/** Orders a list: "default" keeps CLDR order, "name" sorts by name, "newest" puts the latest Emoji version first. */
export declare function sortItems(items: PickerEmoji[], mode: SortMode): PickerEmoji[];
/**
 * Records a pick in the recent list: newest first, once per base emoji (a toned 👋🏽 replaces 👋), at most
 * `max` entries. Returns a new list.
 */
export declare function recordRecent(list: RecentEntry[], base: string, hexcode: string, max: number, now?: number): RecentEntry[];
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
export declare function searchSections(sections: PickerSection[], term: string): PickerEmoji[];
/**
 * Inserts text into an input or textarea and dispatches `input`, so frameworks see it. At the caret when
 * `caretKnown` (the field has had focus); otherwise at the end, because a field nobody has focused reports
 * its caret at 0 and the text would land before what is already there.
 */
export declare function insertText(target: Insertable, text: string, caretKnown: boolean): void;
/** Reads every data-laranail-emoji-* option on an element, typed. Unknown attributes are ignored. */
export declare function parseOptions(element: Element): ParsedOptions;
/** Loads the payload from the package's API (`…/api/v1`) or straight from a `…/picker` URL. */
export declare class ApiSource implements PickerSource {
    private readonly init;
    readonly url: string;
    constructor(url: string, init?: RequestInit);
    load(locale?: string | null): Promise<PickerPayload>;
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
type ResolvedOptions = Required<Omit<PickerOptions, 'source' | 'target' | 'store'>> & Pick<PickerOptions, 'source' | 'target' | 'store'>;
export declare class Picker {
    /** Starts a picker on an element; chain the options, then mount(). */
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
    private tones;
    private body;
    private status;
    private handlers;
    private cleanup;
    private caretKnown;
    private byHex;
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
    destroy(): void;
    open(): void;
    close(): void;
    focusCell(cell: HTMLElement | null | undefined): void;
    private listen;
    private emit;
    /**
     * A field nobody has focused reports its caret at 0, so the first pick would land before text already in
     * it. The caret is trusted once the field has had focus; until then, picks append.
     */
    private watchTarget;
    private buildShell;
    private render;
    private sections;
    private index;
    private renderTabs;
    private renderTones;
    private renderBody;
    private cell;
    private customCell;
    private onKey;
    private select;
}
/** Mounts a picker from its data-laranail-emoji-* attributes. Idempotent: a mounted element is left alone. */
export declare function mountElement(element: HTMLElement): Picker;
/**
 * Mounts every [data-laranail-emoji-picker] under root now and as they are added later, with one
 * MutationObserver. Returns a function that stops observing.
 */
export declare function autoInit(root?: Document | Element): () => void;
export {};
