import { useCallback, useEffect, useId, useRef, useState, type CSSProperties, type KeyboardEvent, type RefObject } from 'react';
import {
  DEFAULT_STRINGS,
  Popover,
  TAB_ICONS,
  TONE_SWATCHES,
  charOf,
  clampColumns,
  customCode,
  gridTarget,
  insertText,
  parsePlacement,
  resultText,
  rovingIndex,
  spySections,
  type Insertable,
  type Placement,
  type PickerCustom,
  type PickerEmoji,
  type PickerSection,
  type PickerText,
} from '../scripts/picker.js';
import { useEmojiPicker, type PickerItem, type UseEmojiPickerOptions } from './useEmojiPicker.js';

export interface EmojiPickerProps extends UseEmojiPickerOptions {
  /** An input, a textarea or a contenteditable element to insert picks into, at its caret (or at the end until it has had focus). */
  target?: RefObject<Insertable | null>;
  /** Always open, in the flow, instead of a button and popover. */
  inline?: boolean;
  /** Close the popover after a pick (default true). */
  closeOnSelect?: boolean;
  /** Grid columns; arrow keys move by this. */
  columns?: number;
  /** What the trigger button shows (default 🙂). */
  trigger?: string;
  /** Where the popover opens: 'auto' (below, flipping above), or top/bottom/start/end, optionally -start/-end. */
  placement?: Placement;
  /** Gap between the trigger and the popover, in px (default 8). */
  offset?: number;
  /** Draw the caret pointing at the trigger (default true). */
  arrow?: boolean;
  /** At or below this viewport width the popover is a bottom sheet (default 640; 0 never). */
  sheetBreakpoint?: number;
  className?: string;
}

const P = 'laranail-emoji-picker';
const keyOf = (item: PickerItem): string => ('image' in item ? `custom:${item.name}` : 'text' in item ? `text:${item.text}` : item.hexcode);

/** A category tab's face: its outline icon (the same paths the vanilla picker draws), else a glyph or label. */
function TabFace({ section }: { section: PickerSection }) {
  const icon = TAB_ICONS[section.slug];

  if (icon) {
    return (
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
        {icon.map((d) => (
          <path key={d} d={d} />
        ))}
      </svg>
    );
  }

  const first = section.items[0];

  if (section.text) return <>{first ? (first as PickerText).text.slice(0, 4) : section.label.slice(0, 2)}</>;

  return <>{first && !section.custom ? charOf((first as PickerEmoji).pick ?? (first as PickerEmoji).hexcode) : '★'}</>;
}

interface Preview {
  glyph: string;
  image: string | null;
  name: string;
  code: string | null;
}

/**
 * The emoji picker as a React component: the same markup, classes and ARIA as the vanilla picker, so
 * picker.css styles it and the same keyboard and screen-reader behaviour holds. State comes from
 * useEmojiPicker(); keyboard moves come from the same gridTarget() and rovingIndex() the vanilla picker uses.
 *
 *   <EmojiPicker source={source} target={textareaRef} onSelect={({ emoji }) => …} />
 */
export function EmojiPicker({
  target,
  inline = false,
  closeOnSelect = true,
  columns: columnsProp = 8,
  trigger: triggerGlyph = '🙂',
  placement = 'auto',
  offset = 8,
  arrow = true,
  sheetBreakpoint = 640,
  className,
  ...options
}: EmojiPickerProps) {
  const state = useEmojiPicker(options);
  const { strings } = state;
  const columns = clampColumns(columnsProp);
  const id = useId();
  const [open, setOpen] = useState(inline);
  const [activeCell, setActiveCell] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<string | null>(null);
  const root = useRef<HTMLDivElement>(null);
  const body = useRef<HTMLDivElement>(null);
  const search = useRef<HTMLInputElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const panel = useRef<HTMLDivElement>(null);
  const arrowEl = useRef<HTMLDivElement>(null);
  const backdrop = useRef<HTMLDivElement>(null);
  const handle = useRef<HTMLDivElement>(null);
  const popover = useRef<Popover | null>(null);
  const swipe = useRef<{ x: number; y: number } | null>(null);
  const caretKnown = useRef(false);
  const watched = useRef<Insertable | null>(null);
  const [scrollTo, setScrollTo] = useState<string | null>(null);
  const { commitRecents } = state;

  // A field nobody has focused reports its caret at 0; trust the caret only once it has had focus. Checked
  // after every render, because a ref keeps its identity while the element in it changes (a field mounted
  // after the picker, or remounted by a key change).
  useEffect(() => {
    const field = target?.current ?? null;

    if (field === watched.current) {
      return;
    }

    watched.current = field;

    if (!field) {
      return;
    }

    caretKnown.current = field.ownerDocument.activeElement === field;
    const onFocus = (): void => {
      caretKnown.current = true;
    };

    field.addEventListener('focus', onFocus);

    return () => field.removeEventListener('focus', onFocus);
  });

  // A tab clears the search and scrolls the body — never the page — once its section is back on screen.
  useEffect(() => {
    if (scrollTo !== null && state.query === '' && body.current) {
      const section = body.current.querySelector<HTMLElement>(`section[data-laranail-emoji-section="${scrollTo}"]`);

      if (section) {
        body.current.scrollTop = section.offsetTop - body.current.offsetTop;
      }

      setScrollTo(null);
    }
  }, [scrollTo, state.query, state.sections]);

  // The same Popover the vanilla picker uses: top layer, placement, caret, and the phone sheet.
  useEffect(() => {
    if (!open || inline || !root.current || !trigger.current || !panel.current) {
      return;
    }

    const controller = new Popover(root.current, trigger.current, panel.current, arrowEl.current, backdrop.current, handle.current, {
      placement: parsePlacement(placement),
      offset,
      arrow,
      sheetBreakpoint,
      onDismiss: () => setOpen(false),
    });

    popover.current = controller;
    controller.open();
    commitRecents();

    // On a phone sheet, focusing search would raise the keyboard over half the emoji; focus the sheet itself.
    if (controller.isSheet) {
      panel.current.focus();
    } else {
      search.current?.focus();
    }

    return () => {
      controller.close();
      popover.current = null;
    };
  }, [open, inline, commitRecents, placement, offset, arrow, sheetBreakpoint]);

  const close = useCallback(
    (focus: 'trigger' | 'target' | 'none' = 'trigger'): void => {
      if (inline) {
        return;
      }

      setOpen(false);

      if (focus === 'target' && target?.current) {
        target.current.focus();
      } else if (focus !== 'none') {
        trigger.current?.focus();
      }
    },
    [inline, target],
  );

  // A popover closes when the user clicks away from it, leaving focus where they put it.
  useEffect(() => {
    if (!open || inline) {
      return;
    }

    const onPointerDown = (event: PointerEvent | Event): void => {
      if (root.current && !root.current.contains(event.target as Node)) {
        close('none');
      }
    };

    document.addEventListener('pointerdown', onPointerDown);

    return () => document.removeEventListener('pointerdown', onPointerDown);
  }, [open, inline, close]);

  const [preview, setPreview] = useState<Preview | null>(null);
  const features = state.features;

  const showPreview = (item: PickerItem): void => {
    if (!features.preview) return;

    if ('text' in item) {
      setPreview({ glyph: item.text, image: null, name: item.name, code: null });
    } else if ('image' in item) {
      setPreview({ glyph: '', image: item.image, name: item.label, code: customCode(item.name, state.data) });
    } else {
      setPreview({ glyph: charOf(state.hexcodeOf(item)), image: null, name: item.name, code: item.shortcode ? customCode(item.shortcode, state.data) : null });
    }
  };

  // The tab follows the scroll: whichever section is at the top of the body is the current one.
  const searching = state.resultCount !== null;
  const sectionKey = state.sections.map((section) => section.slug).join(',');

  useEffect(() => {
    if (searching || !body.current) return;

    return spySections(body.current, setActiveTab);
  }, [searching, sectionKey]);

  const pick = (item: PickerItem): void => {
    // Insert first, then report: an onSelect handler that reads the field sees the pick, as in the vanilla picker.
    const field = target?.current;

    if (field) {
      insertText(field, state.detailOf(item).emoji, caretKnown.current);
    }

    state.select(item);

    if (closeOnSelect && !inline) {
      close(field ? 'target' : 'trigger');
    }
  };

  const tabs = state.tabs;
  const selectedTab = tabs.some((t) => t.slug === activeTab) ? activeTab : (tabs[0]?.slug ?? null);
  const rtl = (): boolean => (root.current?.closest('[dir]')?.getAttribute('dir') ?? document.documentElement.getAttribute('dir')) === 'rtl';

  const showSection = (slug: string): void => {
    setActiveTab(slug);
    state.setQuery('');
    setScrollTo(slug);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
    const element = event.target as Element;

    if (event.key === 'Escape') {
      // Only a popover that actually closes takes the key; otherwise it reaches the page.
      if (!inline && open) {
        event.preventDefault();
        event.stopPropagation();
        close();
      }

      return;
    }

    const tab = element.closest<HTMLElement>('[role="tab"]');
    const radio = element.closest<HTMLElement>('[role="radio"]');
    const kindTab = tab?.closest('[data-laranail-emoji-kinds]') ? tab : null;

    if (kindTab) {
      const next = rovingIndex(state.kinds.length, state.kinds.indexOf(state.kind), event.key, rtl());

      if (next !== null) {
        event.preventDefault();
        state.setKind(state.kinds[next] ?? 'emoji');
        setActiveTab(null);
      }

      return;
    }

    if (tab || radio) {
      const items = [...(tab?.parentElement ?? radio?.parentElement)!.querySelectorAll<HTMLElement>(tab ? '[role="tab"]' : '[role="radio"]')];
      const next = rovingIndex(items.length, items.indexOf((tab ?? radio)!), event.key, rtl());

      if (next !== null) {
        event.preventDefault();
        items[next]?.focus();

        if (tab) {
          showSection(items[next]?.getAttribute('data-laranail-emoji-section') ?? '');
        } else {
          state.setTone(next);
        }
      }

      return;
    }

    const cell = element.closest<HTMLElement>('[role="gridcell"]');

    if (!cell) {
      if (event.key === 'ArrowDown' && event.target === search.current) {
        event.preventDefault();
        body.current?.querySelector<HTMLElement>('[role="gridcell"]')?.focus();
      }

      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      cell.click();

      return;
    }

    const next = body.current ? gridTarget(body.current, cell, event.key, rtl()) : null;

    if (next !== null || ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'PageUp', 'PageDown', 'Home', 'End'].includes(event.key)) {
      event.preventDefault();
    }

    if (next === 'search') {
      search.current?.focus();
    } else if (next) {
      next.focus();
    }
  };

  // One tab stop in the grid: the cell last focused, or the first cell.
  const visible = state.sections.filter((section) => section.items.length > 0);
  const firstKey = visible[0]?.items[0] ? keyOf(visible[0].items[0]) : null;
  const allKeys = new Set(visible.flatMap((section) => (section.items as PickerItem[]).map(keyOf)));
  const stop = activeCell !== null && allKeys.has(activeCell) ? activeCell : firstKey;
  let stopUsed = false;
  const tabIndexFor = (item: PickerItem): number => {
    if (!stopUsed && keyOf(item) === stop) {
      stopUsed = true;

      return 0;
    }

    return -1;
  };

  const style = { [`--${P}-columns`]: String(columns) } as CSSProperties;
  const status =
    state.status === 'loading' ? strings.loading
    : state.status === 'error' ? strings.failed
    : state.resultCount === null ? ''
    : resultText(strings, state.resultCount);
  const sectionId = (slug: string): string => `${id}-section-${slug}`;

  return (
    <div
      ref={root}
      className={className ? `${P} ${className}` : P}
      style={style}
      onBlur={(event) => {
        const next = event.relatedTarget as Node | null;

        if (next !== null && root.current && !root.current.contains(next)) {
          close('none');
          commitRecents();
        }
      }}
      onPointerLeave={() => {
        if (inline && !root.current?.contains(document.activeElement)) {
          commitRecents();
        }
      }}
    >
      {!inline && (
        <button
          ref={trigger}
          type="button"
          className={`${P}-trigger`}
          aria-haspopup="dialog"
          aria-expanded={open}
          aria-controls={`${id}-panel`}
          aria-label={strings.open}
          onClick={() => (open ? close() : setOpen(true))}
        >
          {triggerGlyph || '🙂'}
        </button>
      )}
      {!inline && <div ref={backdrop} className={`${P}-backdrop`} aria-hidden="true" hidden />}
      <div
        ref={panel}
        id={`${id}-panel`}
        className={`${P}-panel`}
        role={inline ? 'group' : 'dialog'}
        aria-label={strings.open}
        hidden={!open}
        tabIndex={inline ? undefined : -1}
        onKeyDown={onKeyDown}
      >
        {!inline && <div ref={arrowEl} className={`${P}-arrow`} aria-hidden="true" hidden={!arrow} />}
        {!inline && <div ref={handle} className={`${P}-handle`} aria-hidden="true" />}
        <input
          ref={search}
          type="search"
          className={`${P}-search`}
          placeholder={strings.search}
          aria-label={strings.search}
          autoComplete="off"
          spellCheck={false}
          value={state.query}
          onChange={(event) => state.setQuery(event.target.value)}
          onFocus={() => popover.current?.expand()}
          hidden={!features.search}
        />
        {state.kinds.length > 1 ? (
          <div className={`${P}-kinds`} role="tablist" aria-label={strings.open} data-laranail-emoji-kinds="">
            {state.kinds.map((kind) => (
              <button
                key={kind}
                type="button"
                role="tab"
                className={`${P}-kind`}
                aria-selected={kind === state.kind}
                tabIndex={kind === state.kind ? 0 : -1}
                data-laranail-emoji-kind={kind}
                onClick={() => {
                  state.setKind(kind);
                  setActiveTab(null);
                }}
              >
                {strings[kind] ?? DEFAULT_STRINGS[kind] ?? kind}
              </button>
            ))}
          </div>
        ) : (
          <div className={`${P}-kinds`} hidden />
        )}
        <div className={`${P}-tabs`} role="tablist" aria-label={strings.open} hidden={!features.categoryTabs}>
          {tabs.map((section) => {
            const selected = section.slug === selectedTab;

            return (
              <button
                key={section.slug}
                type="button"
                role="tab"
                className={`${P}-tab`}
                title={section.label}
                aria-label={section.label}
                aria-selected={selected}
                aria-controls={sectionId(section.slug)}
                tabIndex={selected ? 0 : -1}
                data-laranail-emoji-section={section.slug}
                onClick={() => showSection(section.slug)}
              >
                <TabFace section={section} />
              </button>
            );
          })}
        </div>
        <div
          ref={body}
          className={`${P}-body`}
          onPointerDown={(event) => {
            swipe.current = event.pointerType === 'touch' && popover.current?.isSheet ? { x: event.clientX, y: event.clientY } : null;
          }}
          onPointerUp={(event) => {
            // On the phone sheet, a horizontal swipe moves to the next or previous category.
            const start = swipe.current;
            swipe.current = null;

            if (!start) return;

            const dx = event.clientX - start.x;
            const dy = event.clientY - start.y;

            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) return;

            const current = tabs.findIndex((t) => t.slug === selectedTab);
            const next = tabs[current + ((dx < 0) !== rtl() ? 1 : -1)];

            if (next) showSection(next.slug);
          }}
        >
          {visible.map((section) => {
            const rows: PickerItem[][] = [];
            // Kaomoji are wide, so fewer of them fit a row.
            const perRow = section.text && state.kind === 'kaomoji' ? Math.max(1, Math.round(columns / 4)) : columns;

            (section.items as PickerItem[]).forEach((item, index) => {
              if (index % perRow === 0) rows.push([]);
              rows[rows.length - 1]?.push(item);
            });

            return (
              <section key={section.slug} id={sectionId(section.slug)} className={`${P}-section`} data-laranail-emoji-section={section.slug}>
                <div className={`${P}-heading`} id={`${id}-${section.slug}`}>
                  {section.label}
                </div>
                <div
                  className={section.text ? `${P}-grid ${P}-grid-text` : `${P}-grid`}
                  role="grid"
                  aria-labelledby={`${id}-${section.slug}`}
                  style={section.text ? ({ [`--${P}-columns`]: String(perRow) } as CSSProperties) : undefined}
                >
                  {rows.map((row, r) => (
                    <div key={r} role="row" className={`${P}-row`}>
                      {row.map((item) =>
                        'text' in item ? (
                          <button
                            key={item.text}
                            type="button"
                            role="gridcell"
                            tabIndex={tabIndexFor(item)}
                            className={`${P}-cell ${P}-cell-text`}
                            title={item.name}
                            aria-label={item.name}
                            data-laranail-emoji-text={item.text}
                            onFocus={() => {
                              setActiveCell(keyOf(item));
                              showPreview(item);
                            }}
                            onPointerOver={() => showPreview(item)}
                            onClick={() => pick(item)}
                          >
                            {item.text}
                          </button>
                        ) : 'image' in item ? (
                          <button key={item.name} type="button" role="gridcell" tabIndex={tabIndexFor(item)} className={`${P}-cell`} title={item.label} aria-label={item.label} data-laranail-emoji-custom={item.name} onFocus={() => { setActiveCell(keyOf(item)); showPreview(item); }} onPointerOver={() => showPreview(item)} onClick={() => pick(item)}>
                            <img src={item.image} alt="" className="laranail-emoji laranail-emoji-image" draggable={false} loading="lazy" />
                          </button>
                        ) : (
                          <button
                            key={item.hexcode}
                            type="button"
                            role="gridcell"
                            tabIndex={tabIndexFor(item)}
                            className={`${P}-cell`}
                            title={item.name}
                            aria-label={item.name}
                            data-laranail-emoji-hexcode={state.hexcodeOf(item)}
                            data-laranail-emoji-base={item.hexcode}
                            onFocus={() => {
                              setActiveCell(keyOf(item));
                              showPreview(item);
                            }}
                            onPointerOver={() => showPreview(item)}
                            onClick={() => pick(item)}
                          >
                            {charOf(state.hexcodeOf(item))}
                          </button>
                        ),
                      )}
                    </div>
                  ))}
                </div>
              </section>
            );
          })}
        </div>
        <div className={`${P}-footer`}>
          {features.preview && (
            // Decoration for sighted users: each cell already carries its name for assistive tech.
            <div className={`${P}-preview`} aria-hidden="true">
              {preview && (
                <>
                  <span className={`${P}-preview-glyph`}>{preview.image ? <img src={preview.image} alt="" className="laranail-emoji laranail-emoji-image" /> : preview.glyph}</span>
                  <span className={`${P}-preview-text`}>
                    <span className={`${P}-preview-name`}>{preview.name}</span>
                    {preview.code && <span className={`${P}-preview-code`}>{preview.code}</span>}
                  </span>
                </>
              )}
            </div>
          )}
        <div className={`${P}-tones`} role="radiogroup" aria-label={strings.tone} hidden={!features.skinTones}>
          {TONE_SWATCHES.map((hand, tone) => (
            <button
              key={tone}
              type="button"
              role="radio"
              className={`${P}-tone`}
              aria-checked={tone === state.tone}
              aria-label={strings.tones[tone]}
              tabIndex={tone === state.tone ? 0 : -1}
              data-laranail-emoji-tone={tone}
              onClick={() => state.setTone(tone)}
            >
              {hand}
            </button>
          ))}
        </div>
        </div>
        <div className={`${P}-status`} role="status" aria-live="polite">
          {status}
        </div>
      </div>
    </div>
  );
}
