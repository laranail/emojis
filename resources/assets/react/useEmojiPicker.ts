import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  DEFAULT_STRINGS,
  MAX_RESULTS,
  buildSections,
  capPayload,
  charOf,
  clampTone,
  customCode,
  detectFlagsOnce,
  detectMaxVersionOnce,
  parseRender,
  kindsOf,
  readFeatures,
  textSections,
  indexPayload,
  localStorageStore,
  readRecent,
  recordRecent,
  searchResults,
  withTone,
  type PickerCustom,
  type PickerEmoji,
  type PickerFeatures,
  type PickerImageSet,
  type RenderMode,
  type PickerKind,
  type PickerText,
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
  /** Parts to switch off: { recents: false, custom: false, … }. Everything is on by default. */
  features?: Partial<PickerFeatures>;
  /** 'auto' (default): the device's emoji, images for what it cannot draw; 'native': its own only; 'image': all images. */
  render?: RenderMode;
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
  /** The features in effect: every one on unless the options switched it off. */
  features: PickerFeatures;
  /** How cells draw, and the image set they draw from (null for none). */
  renderMode: RenderMode;
  imageSet: PickerImageSet | null;
  /** The sets the switcher offers (empty without it), and the choice: 'native' or a set's name. */
  imageSets: PickerImageSet[];
  chosenSet: string;
  setChosenSet: (set: string) => void;
  /** The kinds the payload offers (emoji, then kaomoji and symbols when it carries them), and the one shown. */
  kinds: PickerKind[];
  kind: PickerKind;
  setKind: (kind: PickerKind) => void;
  /** What to draw: the sections, or the search result sections while a query is typed. */
  sections: PickerSection[];
  /** The sections a tab bar names, whatever is being searched; empty ones are left out. */
  tabs: PickerSection[];
  /** The number of results while searching, else null. */
  resultCount: number | null;
  /** The hexcode a cell shows for an emoji under the current tone (a recent shows the form it was picked in). */
  hexcodeOf: (item: PickerEmoji) => string;
  /** What picking an item would produce, without recording it: insert this, then call select(). */
  detailOf: (item: PickerItem) => SelectDetail;
  /** Picks an item: records an emoji as recent, calls onSelect, and returns the detail. */
  select: (item: PickerItem) => SelectDetail;
  /**
   * Shows the picks made since the last call in Frequently used. A pick is not shown at once, so the grid
   * does not move under the pointer; <EmojiPicker /> calls this when the popover opens or the pointer leaves.
   */
  commitRecents: () => void;
}

/** Anything a cell can hold: an emoji, a custom emoji, or a kaomoji or symbol. */
export type PickerItem = PickerEmoji | PickerCustom | PickerText;

const isCustom = (item: PickerItem): item is PickerCustom => 'image' in item;
const isText = (item: PickerItem): item is PickerText => 'text' in item;

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
  const [raw, setRaw] = useState<PickerPayload | null>(null);
  const [support, setSupport] = useState<{ version: string | null; flags: boolean | null }>({ version: null, flags: null });
  const [chosen, setChosen] = useState<string>('native');
  const [status, setStatus] = useState<EmojiPickerState['status']>('loading');
  const [error, setError] = useState<unknown>(null);
  const [query, setQuery] = useState('');
  const [term, setTerm] = useState('');
  const [tone, setToneState] = useState<number>(clampTone(options.tone ?? 0));
  const [recent, setRecent] = useState<RecentEntry[]>([]);
  const [kind, setKindState] = useState<PickerKind>('emoji');
  const featureKey = JSON.stringify(options.features ?? {});
  const features = useMemo(() => readFeatures(JSON.parse(featureKey)), [featureKey]);
  const onSelect = useRef(options.onSelect);
  onSelect.current = options.onSelect;
  const initialTone = useRef(options.tone);
  initialTone.current = options.tone;

  useEffect(() => {
    const stored = store.get('tone');

    setToneState(stored === null || stored === undefined ? clampTone(initialTone.current ?? 0) : clampTone(stored));
    setRecent(readRecent(store.get('recent')));
    const set = store.get('set');
    setChosen(typeof set === 'string' ? set : 'native');
  }, [store]);

  useEffect(() => {
    let live = true;
    const controller = typeof AbortController === 'undefined' ? null : new AbortController();

    setStatus('loading');
    Promise.all([source.load(locale, controller?.signal), maxVersion === 'auto' ? detectMaxVersionOnce() : Promise.resolve(maxVersion), detectFlagsOnce()]).then(
      ([payload, version, flags]) => {
        if (live) {
          setRaw(payload);
          setSupport({ version: version ?? null, flags });
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
  const renderOption = parseRender(options.render);
  const imageSets = useMemo(() => (features.setSwitcher ? (raw?.imageSets ?? []) : []), [raw, features.setSwitcher]);
  const picked = imageSets.find((set) => set.set === chosen) ?? null;
  const imageSet = picked ?? raw?.images ?? null;
  const renderMode: RenderMode = picked ? 'image' : renderOption;
  // The payload capped for this device, falling back to images where the mode allows.
  const data = useMemo(
    () => (raw ? capPayload(raw, support.version, undefined, renderMode === 'native' ? {} : { set: imageSet, flags: support.flags }) : null),
    [raw, support, renderMode, imageSet],
  );
  const setChosenSet = useCallback(
    (set: string) => {
      store.set('set', set);
      setChosen(set);
    },
    [store],
  );
  const kinds = useMemo(() => kindsOf(data), [data]);
  const shown: PickerKind = kinds.includes(kind) ? kind : 'emoji';
  const base = useMemo((): PickerSection[] => {
    if (!data) return [];
    if (shown !== 'emoji') return textSections(data, shown);

    return buildSections(data, { categories: wanted, sort, recent: features.recents ? recent : [], recentOrder, strings }).filter((section) => features.custom || !section.custom);
  }, [data, shown, wanted, sort, recent, recentOrder, strings, features]);
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

  const effectiveTone = features.skinTones ? tone : 0;
  const hexcodeOf = useCallback((item: PickerEmoji) => item.pick ?? withTone(item, effectiveTone), [effectiveTone]);

  const setKind = useCallback((next: PickerKind) => {
    setKindState(next);
    setQuery('');
  }, []);

  const detailOf = useCallback(
    (item: PickerItem): SelectDetail => {
      if (isText(item)) {
        return { emoji: item.text, hexcode: null, name: item.name, shortcode: null, custom: false, kind: shown };
      }

      if (isCustom(item)) {
        return { emoji: customCode(item.name, data), hexcode: null, name: item.label, shortcode: item.name, custom: true };
      }

      const hexcode = item.pick ?? withTone(item, effectiveTone);
      const known = index.get(item.hexcode) ?? item;

      return { emoji: charOf(hexcode), hexcode, name: known.name, shortcode: known.shortcode, custom: false };
    },
    [effectiveTone, index, data, shown],
  );

  const select = useCallback(
    (item: PickerItem): SelectDetail => {
      const detail = detailOf(item);

      if (features.recents && !detail.custom && detail.hexcode !== null) {
        store.set('recent', recordRecent(store.get('recent'), (item as PickerEmoji).hexcode, detail.hexcode, maxRecent));
      }

      onSelect.current?.(detail);

      return detail;
    },
    [detailOf, store, maxRecent, features],
  );

  const commitRecents = useCallback(() => setRecent(readRecent(store.get('recent'))), [store]);

  return {
    status,
    error,
    data,
    strings,
    query,
    setQuery,
    tone: features.skinTones ? tone : 0,
    setTone,
    features,
    renderMode,
    imageSet,
    imageSets,
    chosenSet: picked ? chosen : 'native',
    setChosenSet,
    kinds,
    kind: shown,
    setKind,
    sections: results ?? base,
    tabs,
    resultCount: results ? results.reduce((sum, section) => sum + section.items.length, 0) : null,
    hexcodeOf,
    detailOf,
    select,
    commitRecents,
  };
}
