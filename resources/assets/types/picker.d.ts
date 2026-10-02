// Types for public/assets/js/picker.js (laranail/emojis). Hand-written; `npm run typecheck` holds them to the
// module's exports.

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
  get(key: string): any;
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
  /** Hide emoji newer than this Emoji version; 'auto' (default) detects what the browser draws, null shows all. */
  maxVersion?: string | 'auto' | null;
  closeOnSelect?: boolean;
  inline?: boolean;
  userKey?: string;
  store?: PickerStore;
  strings?: Partial<PickerStrings>;
}

export interface PickerEvents {
  select: SelectDetail;
  ready: { picker: Picker };
  error: { error: unknown };
}

export declare class Picker {
  static create(element: HTMLElement, options?: PickerOptions): Picker;
  constructor(element: HTMLElement, options?: PickerOptions);
  readonly element: HTMLElement;
  source(source: PickerSource): this;
  locale(locale: string | null): this;
  skinTone(tone: number): this;
  sort(mode: SortMode): this;
  categories(list: string[]): this;
  columns(count: number): this;
  maxVersion(version: string | 'auto' | null): this;
  closeOnSelect(close?: boolean): this;
  inline(inline?: boolean): this;
  target(target: string | HTMLInputElement | HTMLTextAreaElement | null): this;
  history(options: { max?: number; store?: PickerStore; order?: RecentOrder }): this;
  on<K extends keyof PickerEvents>(event: K, handler: (detail: PickerEvents[K]) => void): this;
  mount(): Promise<this>;
  open(): void;
  close(): void;
  destroy(): void;
}

export declare class ApiSource implements PickerSource {
  constructor(url: string, init?: RequestInit);
  load(locale?: string | null): Promise<PickerPayload>;
}

export declare class StaticSource implements PickerSource {
  constructor(payload: PickerPayload | string);
  load(): Promise<PickerPayload>;
}

export declare function memoryStore(): PickerStore;
export declare function byVersion(a: string, b: string): number;
export declare function detectMaxVersion(doc?: Document): string | null;
export declare function localStorageStore(namespace?: string): PickerStore;
export declare function fold(text: string): string;
export declare function charOf(hexcode: string): string;
export declare function withTone(item: PickerEmoji, tone: number): string;
export declare function search(items: PickerEmoji[], term: string): PickerEmoji[];
export declare function sortItems(items: PickerEmoji[], mode: SortMode): PickerEmoji[];
export declare function recordRecent(list: RecentEntry[], base: string, hexcode: string, max: number, now?: number): RecentEntry[];
export declare function orderRecent(list: RecentEntry[], mode: RecentOrder): RecentEntry[];
export declare function parseOptions(element: Element): Required<Omit<PickerOptions, 'source' | 'target' | 'store' | 'strings' | 'maxVersion'>> & {
  maxVersion: string;
  target: string | null;
  source: string | null;
  payload: string | null;
  strings: Partial<PickerStrings>;
};
export declare function mountElement(element: HTMLElement): Picker;
export declare function autoInit(root?: Document | Element): () => void;
