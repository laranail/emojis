import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  DEFAULT_STRINGS,
  MAX_RESULTS,
  buildSections,
  capPayload,
  charOf,
  clampTone,
  customCode,
  detectMaxVersionOnce,
  indexPayload,
  localStorageStore,
  readRecent,
  recordRecent,
  searchResults,
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
} from '../scripts/picker.js';

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
  /** Milliseconds to wait after the last keystroke before searching (default 80). */
  searchDelay?: number;
  onSelect?: (detail: SelectDetail) => void;
}

export interface EmojiPickerState {
  status: 'loading' | 'ready' | 'error';
  error: unknown;
  data: PickerPayload | null;
  strings: PickerStrings;
  /** What the search field shows; the results follow it after `searchDelay`. */
  query: string;
  setQuery: (query: string) => void;
  tone: number;
  setTone: (tone: number) => void;
  /** What to draw: the sections, or the search result sections while a query is typed. */
  sections: PickerSection[];
  /** The sections a tab bar names, whatever is being searched; empty ones are left out. */
  tabs: PickerSection[];
  /** The number of results while searching, else null. */
  resultCount: number | null;
  /** The hexcode a cell shows for an emoji under the current tone (a recent shows the form it was picked in). */
  hexcodeOf: (item: PickerEmoji) => string;
  /** What picking an item would produce, without recording it: insert this, then call select(). */
  detailOf: (item: PickerEmoji | PickerCustom) => SelectDetail;
  /** Picks an emoji or a custom one: records it as recent, calls onSelect, and returns the detail. */
  select: (item: PickerEmoji | PickerCustom) => SelectDetail;
  /**
   * Shows the picks made since the last call in Frequently used. A pick is not shown at once, so the grid
   * does not move under the pointer; <EmojiPicker /> calls this when the popover opens or the pointer leaves.
   */
  commitRecents: () => void;
}

const isCustom = (item: PickerEmoji | PickerCustom): item is PickerCustom => 'image' in item;

/**
 * The picker's state for React, over the same pure functions the vanilla Picker uses (buildSections,
 * searchResults, capPayload, recordRecent, withTone), so the two cannot behave differently. Render it
 * yourself, or use <EmojiPicker />.
 *
 * Stored values (tone, recents) are read in an effect, not during render, so a server render and the first
 * client render agree; they are read again when `userKey` or `store` changes, so one user's recents never
 * show for the next.
 */
export function useEmojiPicker(options: UseEmojiPickerOptions): EmojiPickerState {
  const { source, locale = null, maxVersion = 'auto', userKey = '', categories, sort = 'default', recentOrder = 'recent', maxRecent = 36, searchDelay = 80 } = options;
  const store = useMemo(() => options.store ?? localStorageStore(userKey ? `laranail-emoji:${userKey}` : 'laranail-emoji'), [options.store, userKey]);
  const strings = useMemo(() => ({ ...DEFAULT_STRINGS, ...options.strings }), [options.strings]);
  const [data, setData] = useState<PickerPayload | null>(null);
  const [status, setStatus] = useState<EmojiPickerState['status']>('loading');
  const [error, setError] = useState<unknown>(null);
  const [query, setQuery] = useState('');
  const [term, setTerm] = useState('');
  const [tone, setToneState] = useState<number>(clampTone(options.tone ?? 0));
  const [recent, setRecent] = useState<RecentEntry[]>([]);
  const onSelect = useRef(options.onSelect);
  onSelect.current = options.onSelect;
  const initialTone = useRef(options.tone);
  initialTone.current = options.tone;

  useEffect(() => {
    const stored = store.get('tone');

    setToneState(stored === null || stored === undefined ? clampTone(initialTone.current ?? 0) : clampTone(stored));
    setRecent(readRecent(store.get('recent')));
  }, [store]);

  useEffect(() => {
    let live = true;
    const controller = typeof AbortController === 'undefined' ? null : new AbortController();

    setStatus('loading');
    Promise.all([source.load(locale, controller?.signal), maxVersion === 'auto' ? detectMaxVersionOnce() : Promise.resolve(maxVersion)]).then(
      ([payload, cap]) => {
        if (live) {
          setData(capPayload(payload, cap));
          setError(null);
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
      controller?.abort();
    };
  }, [source, locale, maxVersion]);

  // The search follows the field after a pause in typing, so a keystroke does not redraw ~1,900 cells.
  useEffect(() => {
    if (searchDelay <= 0 || query === '') {
      setTerm(query);

      return;
    }

    const timer = setTimeout(() => setTerm(query), searchDelay);

    return () => clearTimeout(timer);
  }, [query, searchDelay]);

  // Compared by content, so an inline array prop does not rebuild every section on every render.
  const categoryKey = (categories ?? []).join('\u0000');
  const wanted = useMemo(() => (categoryKey === '' ? [] : categoryKey.split('\u0000')), [categoryKey]);
  const base = useMemo(
    () => (data ? buildSections(data, { categories: wanted, sort, recent, recentOrder, strings }) : []),
    [data, wanted, sort, recent, recentOrder, strings],
  );
  const trimmed = term.trim();
  const results = useMemo(() => (trimmed ? searchResults(base, trimmed, strings, MAX_RESULTS) : null), [base, trimmed, strings]);
  const tabs = useMemo(() => base.filter((section) => section.items.length > 0), [base]);
  const index = useMemo(() => (data ? indexPayload(data) : new Map<string, PickerEmoji>()), [data]);

  const setTone = useCallback(
    (next: number) => {
      const clamped = clampTone(next);

      store.set('tone', clamped);
      setToneState(clamped);
    },
    [store],
  );

  const hexcodeOf = useCallback((item: PickerEmoji) => item.pick ?? withTone(item, tone), [tone]);

  const detailOf = useCallback(
    (item: PickerEmoji | PickerCustom): SelectDetail => {
      if (isCustom(item)) {
        return { emoji: customCode(item.name, data), hexcode: null, name: item.label, shortcode: item.name, custom: true };
      }

      const hexcode = item.pick ?? withTone(item, tone);
      const known = index.get(item.hexcode) ?? item;

      return { emoji: charOf(hexcode), hexcode, name: known.name, shortcode: known.shortcode, custom: false };
    },
    [tone, index, data],
  );

  const select = useCallback(
    (item: PickerEmoji | PickerCustom): SelectDetail => {
      const detail = detailOf(item);

      if (!detail.custom && detail.hexcode !== null) {
        store.set('recent', recordRecent(store.get('recent'), (item as PickerEmoji).hexcode, detail.hexcode, maxRecent));
      }

      onSelect.current?.(detail);

      return detail;
    },
    [detailOf, store, maxRecent],
  );

  const commitRecents = useCallback(() => setRecent(readRecent(store.get('recent'))), [store]);

  return {
    status,
    error,
    data,
    strings,
    query,
    setQuery,
    tone,
    setTone,
    sections: results ?? base,
    tabs,
    resultCount: results ? results.reduce((sum, section) => sum + section.items.length, 0) : null,
    hexcodeOf,
    detailOf,
    select,
    commitRecents,
  };
}
