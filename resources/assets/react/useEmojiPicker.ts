import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  DEFAULT_STRINGS,
  buildSections,
  capPayload,
  charOf,
  indexPayload,
  localStorageStore,
  recordRecent,
  searchSections,
  withTone,
  type PickerCustom,
  type PickerEmoji,
  type PickerPayload,
  type PickerSection,
  type PickerSource,
  type PickerStore,
  type PickerStrings,
  type RecentEntry,
  type RecentOrder,
  type SelectDetail,
  type SortMode,
} from '../scripts/picker';

export interface UseEmojiPickerOptions {
  /** Where the payload comes from: new ApiSource(url) or new StaticSource(payload). */
  source: PickerSource;
  locale?: string | null;
  /** The tone before the user picks one (0–5); a stored choice wins. */
  tone?: number;
  maxRecent?: number;
  recentOrder?: RecentOrder;
  sort?: SortMode;
  categories?: string[];
  /** Hide emoji newer than this Emoji version: 'auto' (default) asks the browser, null shows all. */
  maxVersion?: string | null;
  userKey?: string;
  store?: PickerStore;
  strings?: Partial<PickerStrings>;
  onSelect?: (detail: SelectDetail) => void;
}

export interface EmojiPickerState {
  status: 'loading' | 'ready' | 'error';
  error: unknown;
  data: PickerPayload | null;
  strings: PickerStrings;
  query: string;
  setQuery: (query: string) => void;
  tone: number;
  setTone: (tone: number) => void;
  /** What to draw: the sections, or one "search" section of ranked results while a query is typed. */
  sections: PickerSection[];
  /** The sections a tab bar names, whatever is being searched. */
  tabs: PickerSection[];
  /** The number of results while searching, else null. */
  resultCount: number | null;
  /** The hexcode a cell shows for an emoji under the current tone. */
  hexcodeOf: (item: PickerEmoji) => string;
  /** Picks an emoji or a custom one: records it as recent, calls onSelect, and returns the detail. */
  select: (item: PickerEmoji | PickerCustom) => SelectDetail;
}

const isCustom = (item: PickerEmoji | PickerCustom): item is PickerCustom => 'image' in item;

/**
 * The picker's state for React, over the same pure functions the vanilla Picker uses (buildSections,
 * searchSections, capPayload, recordRecent, withTone), so the two cannot behave differently. Render it
 * yourself, or use <EmojiPicker />.
 */
export function useEmojiPicker(options: UseEmojiPickerOptions): EmojiPickerState {
  const { source, locale = null, maxVersion = 'auto', userKey = '', categories, sort = 'default', recentOrder = 'recent', maxRecent = 36 } = options;
  const store = useMemo(() => options.store ?? localStorageStore(userKey ? `laranail-emoji:${userKey}` : 'laranail-emoji'), [options.store, userKey]);
  const strings = useMemo(() => ({ ...DEFAULT_STRINGS, ...options.strings }), [options.strings]);
  const [data, setData] = useState<PickerPayload | null>(null);
  const [status, setStatus] = useState<EmojiPickerState['status']>('loading');
  const [error, setError] = useState<unknown>(null);
  const [query, setQuery] = useState('');
  const [tone, setToneState] = useState<number>(() => (store.get('tone') as number | null) ?? options.tone ?? 0);
  const [recent, setRecent] = useState<RecentEntry[]>(() => (store.get('recent') as RecentEntry[] | null) ?? []);
  const onSelect = useRef(options.onSelect);
  onSelect.current = options.onSelect;

  useEffect(() => {
    let live = true;

    setStatus('loading');
    source.load(locale).then(
      (payload) => {
        if (live) {
          setData(capPayload(payload, maxVersion));
          setStatus('ready');
        }
      },
      (failure: unknown) => {
        if (live) {
          setError(failure);
          setStatus('error');
        }
      },
    );

    return () => {
      live = false;
    };
  }, [source, locale, maxVersion]);

  const base = useMemo(
    () => (data ? buildSections(data, { categories, sort, recent, recentOrder, strings }) : []),
    [data, categories, sort, recent, recentOrder, strings],
  );
  const term = query.trim();
  const results = useMemo(() => (term ? searchSections(base, term) : null), [base, term]);
  const sections: PickerSection[] = results ? [{ slug: 'search', label: strings.search, items: results }] : base;
  const index = useMemo(() => (data ? indexPayload(data) : new Map<string, PickerEmoji>()), [data]);

  const setTone = useCallback(
    (next: number) => {
      const clamped = Math.max(0, Math.min(5, Math.trunc(next) || 0));

      store.set('tone', clamped);
      setToneState(clamped);
    },
    [store],
  );

  const hexcodeOf = useCallback((item: PickerEmoji) => withTone(item, tone), [tone]);

  const select = useCallback(
    (item: PickerEmoji | PickerCustom): SelectDetail => {
      let detail: SelectDetail;

      if (isCustom(item)) {
        detail = { emoji: `:${item.name}:`, hexcode: null, name: item.label, shortcode: item.name, custom: true };
      } else {
        const hexcode = withTone(item, tone);
        const known = index.get(item.hexcode) ?? item;
        const next = recordRecent((store.get('recent') as RecentEntry[] | null) ?? [], item.hexcode, hexcode, maxRecent);

        store.set('recent', next);
        setRecent(next);
        detail = { emoji: charOf(hexcode), hexcode, name: known.name, shortcode: known.shortcode, custom: false };
      }

      onSelect.current?.(detail);

      return detail;
    },
    [tone, index, store, maxRecent],
  );

  return { status, error, data, strings, query, setQuery, tone, setTone, sections, tabs: base, resultCount: results ? results.length : null, hexcodeOf, select };
}
