import { useCallback, useEffect, useId, useRef, useState, type CSSProperties, type KeyboardEvent, type RefObject } from 'react';
import {
  DEFAULT_STRINGS,
  FIRST_PAINT_CELLS,
  Popover,
  TAB_ICONS,
  TONE_SWATCHES,
  bindToneMenu,
  charOf,
  clampColumns,
  customCode,
  drawsImage,
  gridTarget,
  insertText,
  openToneMenu,
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
  /** 'auto' (default) follows the OS or the page's theme; 'light' or 'dark' fixes it. */
  theme?: 'auto' | 'light' | 'dark';
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
  theme = 'auto',
  className,
  ...options
}: EmojiPickerProps) {
  const state = useEmojiPicker(options);
  const { strings } = state;
  const columns = clampColumns(columnsProp);
  const id = useId();
  const [open, setOpen] = useState(inline);
  // A popover's grid is built the first time it opens, not on page load.
  const [opened, setOpened] = useState(inline);
  // How many sections are drawn: about a screenful first, the rest added in idle time.
  const [drawnSections, setDrawnSections] = useState(0);

  useEffect(() => {
    if (open) setOpened(true);
  }, [open]);
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
  const [failed, setFailed] = useState<ReadonlySet<string>>(() => new Set());
  const features = state.features;
  /** An image to draw for this form, unless it failed to load (then the device's glyph). */
  const imageOf = (item: PickerEmoji, hexcode: string): string | null => {
    const url = drawsImage(item, hexcode, state.renderMode, state.imageSet);

    return url && !failed.has(url) ? url : null;
  };
  const imageFailed = (url: string): void => setFailed((previous) => new Set(previous).add(url));

  const showPreview = (item: PickerItem): void => {
    if (!features.preview) return;

    if ('text' in item) {
      setPreview({ glyph: item.text, image: null, name: item.name, code: null });
    } else if ('image' in item) {
      setPreview({ glyph: '', image: item.image, name: item.label, code: customCode(item.name, state.data) });
    } else {
      const hexcode = state.hexcodeOf(item);
      setPreview({ glyph: charOf(hexcode), image: imageOf(item, hexcode), name: item.name, code: item.shortcode ? customCode(item.shortcode, state.data) : null });
    }
  };

  // A right click, Shift+F10 or a long press on an emoji with tones opens the shared tone menu; a form
  // chosen there is picked like any other, its exact tone recorded.
  const latest = useRef({ state, pick: (_: PickerItem): void => {}, imageOf });
  useEffect(() => {
    if (!features.perPersonTones || !body.current) return;

    let closeMenu: (() => void) | null = null;
    const unbind = bindToneMenu(
      body.current,
      (cell) => {
        const base = cell.getAttribute('data-laranail-emoji-base') ?? '';
        const item = latest.current.state.sections.flatMap((section) => (section.custom || section.text ? [] : (section.items as PickerEmoji[]))).find((i) => i.hexcode === base);

        return item && Object.keys(item.skins ?? {}).length > 0 ? item : null;
      },
      (cell, item) => {
        closeMenu?.();
        const { state: current } = latest.current;
        closeMenu = openToneMenu(cell, root.current ?? cell, item, {
          strings: current.strings,
          tone: current.tone,
          mode: current.renderMode,
          set: current.imageSet,
          rtl: rtl(),
          onPick: (hexcode) => latest.current.pick({ ...item, pick: hexcode }),
          onClose: () => {
            closeMenu = null;
          },
        });
      },
    );

    return () => {
      closeMenu?.();
      unbind();
    };
  }, [features.perPersonTones]);

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

  latest.current = { state, pick, imageOf };

  const tabs = state.tabs;
  const selectedTab = tabs.some((t) => t.slug === activeTab) ? activeTab : (tabs[0]?.slug ?? null);
  const rtl = (): boolean => (root.current?.closest('[dir]')?.getAttribute('dir') ?? document.documentElement.getAttribute('dir')) === 'rtl';

  const showSection = (slug: string): void => {
    drawAll();
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
  const allSections = state.sections.filter((section) => section.items.length > 0);
  const firstScreen = (() => {
    let cells = 0;
    let count = 0;

    for (const section of allSections) {
      if (state.resultCount === null && cells >= FIRST_PAINT_CELLS) break;
      cells += section.items.length;
      count++;
    }

    return count;
  })();
  const sectionsKey = allSections.map((section) => `${section.slug}:${section.items.length}`).join(',');

  // A new set of sections starts from a screenful again, then grows a section at a time while idle.
  useEffect(() => {
    setDrawnSections(firstScreen);
  }, [sectionsKey, firstScreen]);

  useEffect(() => {
    if (!opened || drawnSections >= allSections.length) return;

    let cancelled = false;
    const idle = (globalThis as { requestIdleCallback?: (cb: () => void, o?: { timeout: number }) => number }).requestIdleCallback;
    const next = (): void => {
      if (!cancelled) setDrawnSections((n) => n + 1);
    };

    if (idle) idle(next, { timeout: 120 });
    else setTimeout(next, 1);

    return () => {
      cancelled = true;
    };
  }, [opened, drawnSections, allSections.length]);

  /** Draws every section now: a tab or a key that can reach past what is drawn needs them all. */
  const drawAll = (): void => {
    if (drawnSections < allSections.length) setDrawnSections(allSections.length);
  };
  const visible = opened ? allSections.slice(0, Math.max(drawnSections, firstScreen)) : [];
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
      data-theme={theme === 'light' || theme === 'dark' ? theme : undefined}
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
          // Keyboard focus entering the grid draws every section, so End and PageDown can reach them all.
          onFocus={drawAll}
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
                            {(() => {
                              const hexcode = state.hexcodeOf(item);
                              const url = imageOf(item, hexcode);

                              // The cell is named; the image is decoration, and falls back to the glyph if it fails.
                              return url ? <img src={url} alt="" className="laranail-emoji laranail-emoji-image" draggable={false} loading="lazy" decoding="async" onError={() => imageFailed(url)} /> : charOf(hexcode);
                            })()}
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
          {features.setSwitcher && (
            <select
              className={`${P}-set`}
              aria-label={strings.style ?? DEFAULT_STRINGS.style}
              hidden={state.imageSets.length === 0}
              value={state.chosenSet}
              onChange={(event) => state.setChosenSet(event.target.value)}
            >
              <option value="native">{strings.native ?? DEFAULT_STRINGS.native}</option>
              {state.imageSets.map((set) => (
                <option key={set.set} value={set.set}>
                  {set.set.charAt(0).toUpperCase() + set.set.slice(1)}
                </option>
              ))}
            </select>
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
