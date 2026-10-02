import { useEffect, useId, useRef, useState, type CSSProperties, type KeyboardEvent, type RefObject } from 'react';
import { TONE_SWATCHES, charOf, insertText, type PickerCustom, type PickerEmoji, type SelectDetail } from '../scripts/picker';
import { useEmojiPicker, type UseEmojiPickerOptions } from './useEmojiPicker';

type Insertable = HTMLInputElement | HTMLTextAreaElement;

export interface EmojiPickerProps extends UseEmojiPickerOptions {
  /** An input or textarea to insert picks into, at its caret (or at the end until it has had focus). */
  target?: RefObject<Insertable | null>;
  /** Always open, in the flow, instead of a button and popover. */
  inline?: boolean;
  /** Close the popover after a pick (default true). */
  closeOnSelect?: boolean;
  /** Grid columns; arrow keys move by this. */
  columns?: number;
  className?: string;
}

const P = 'laranail-emoji-picker';

/**
 * The emoji picker as a React component: the same markup, classes and ARIA as the vanilla picker, so
 * picker.css styles it and the same keyboard and screen-reader behaviour holds. State comes from
 * useEmojiPicker().
 *
 *   <EmojiPicker source={source} target={textareaRef} onSelect={({ emoji }) => …} />
 */
export function EmojiPicker({ target, inline = false, closeOnSelect = true, columns = 8, className, ...options }: EmojiPickerProps) {
  const state = useEmojiPicker(options);
  const { strings } = state;
  const id = useId();
  const [open, setOpen] = useState(inline);
  const body = useRef<HTMLDivElement>(null);
  const search = useRef<HTMLInputElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const caretKnown = useRef(false);
  const [scrollTo, setScrollTo] = useState<string | null>(null);

  // A field nobody has focused reports its caret at 0; trust the caret only once it has had focus.
  useEffect(() => {
    const field = target?.current;

    if (!field) {
      return;
    }

    caretKnown.current = field.ownerDocument.activeElement === field;
    const onFocus = (): void => {
      caretKnown.current = true;
    };

    field.addEventListener('focus', onFocus);

    return () => field.removeEventListener('focus', onFocus);
  }, [target]);

  // A tab clears the search and scrolls once its section is back on screen.
  useEffect(() => {
    if (scrollTo !== null && state.query === '') {
      body.current?.querySelector(`section[data-laranail-emoji-section="${scrollTo}"]`)?.scrollIntoView?.({ block: 'start' });
      setScrollTo(null);
    }
  }, [scrollTo, state.query]);

  useEffect(() => {
    if (open && !inline) {
      search.current?.focus();
    }
  }, [open, inline]);

  const close = (): void => {
    if (inline) {
      return;
    }

    setOpen(false);
    trigger.current?.focus();
  };

  const pick = (item: PickerEmoji | PickerCustom): void => {
    const detail: SelectDetail = state.select(item);
    const field = target?.current;

    if (field) {
      insertText(field, detail.emoji, caretKnown.current);
    }

    if (closeOnSelect) {
      close();
    }
  };

  const cells = (): HTMLElement[] => [...(body.current?.querySelectorAll<HTMLElement>('[role="gridcell"]') ?? [])];

  const focusCell = (cell: HTMLElement | undefined): void => {
    if (!cell) {
      return;
    }

    for (const other of cells()) {
      other.tabIndex = other === cell ? 0 : -1;
    }

    cell.focus();
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();

      return;
    }

    const cell = (event.target as Element).closest<HTMLElement>('[role="gridcell"]');

    if (!cell) {
      if (event.key === 'ArrowDown' && event.target === search.current) {
        event.preventDefault();
        focusCell(cells()[0]);
      }

      return;
    }

    const all = cells();
    const index = all.indexOf(cell);
    const steps: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: columns, ArrowUp: -columns };
    const step = steps[event.key];

    if (step !== undefined) {
      event.preventDefault();

      if (index + step < 0 && event.key === 'ArrowUp') {
        search.current?.focus();

        return;
      }

      focusCell(all[Math.max(0, Math.min(all.length - 1, index + step))]);
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      focusCell(event.key === 'Home' ? all[0] : all[all.length - 1]);
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      cell.click();
    }
  };

  let first = true;
  const tabIndexOf = (): number => {
    const value = first ? 0 : -1;
    first = false;

    return value;
  };
  const style = { [`--${P}-columns`]: String(columns) } as CSSProperties;
  const status =
    state.status === 'loading' ? strings.loading
    : state.status === 'error' ? strings.failed
    : state.resultCount === null ? ''
    : state.resultCount === 0 ? strings.noResults
    : strings.results.replace('{count}', String(state.resultCount));

  return (
    <div className={className ? `${P} ${className}` : P} style={style}>
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
          🙂
        </button>
      )}
      <div id={`${id}-panel`} className={`${P}-panel`} role="dialog" aria-label={strings.open} hidden={!open} onKeyDown={onKeyDown}>
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
        />
        <div className={`${P}-tabs`} role="tablist">
          {state.tabs.map((section) => {
              const icon = section.custom ? '★' : section.items[0] ? charOf(section.items[0].hexcode) : '★';

              return (
                <button
                  key={section.slug}
                  type="button"
                  role="tab"
                  className={`${P}-tab`}
                  title={section.label}
                  aria-label={section.label}
                  data-laranail-emoji-section={section.slug}
                  onClick={() => {
                    state.setQuery('');
                    setScrollTo(section.slug);
                  }}
                >
                  {icon}
                </button>
              );
            })}
        </div>
        <div ref={body} className={`${P}-body`}>
          {state.sections
            .filter((section) => section.items.length > 0)
            .map((section) => {
              const rows: Array<Array<PickerEmoji | PickerCustom>> = [];

              (section.items as Array<PickerEmoji | PickerCustom>).forEach((item, index) => {
                if (index % columns === 0) rows.push([]);
                rows[rows.length - 1]?.push(item);
              });

              return (
                <section key={section.slug} className={`${P}-section`} data-laranail-emoji-section={section.slug}>
                  <div className={`${P}-heading`} id={`${id}-${section.slug}`}>
                    {section.label}
                  </div>
                  <div className={`${P}-grid`} role="grid" aria-labelledby={`${id}-${section.slug}`}>
                    {rows.map((row, r) => (
                      <div key={r} role="row" className={`${P}-row`}>
                        {row.map((item) =>
                          'image' in item ? (
                            <button key={item.name} type="button" role="gridcell" tabIndex={tabIndexOf()} className={`${P}-cell`} title={item.label} aria-label={item.label} data-laranail-emoji-custom={item.name} onClick={() => pick(item)}>
                              <img src={item.image} alt="" className="laranail-emoji laranail-emoji-image" draggable={false} loading="lazy" />
                            </button>
                          ) : (
                            <button
                              key={item.hexcode}
                              type="button"
                              role="gridcell"
                              tabIndex={tabIndexOf()}
                              className={`${P}-cell`}
                              title={item.name}
                              aria-label={item.name}
                              data-laranail-emoji-hexcode={state.hexcodeOf(item)}
                              data-laranail-emoji-base={item.hexcode}
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
        <div className={`${P}-tones`} role="radiogroup" aria-label={strings.tone}>
          {TONE_SWATCHES.map((hand, tone) => (
            <button
              key={tone}
              type="button"
              role="radio"
              className={`${P}-tone`}
              aria-checked={tone === state.tone}
              aria-label={strings.tones[tone]}
              tabIndex={tone === state.tone ? 0 : -1}
              onClick={() => state.setTone(tone)}
            >
              {hand}
            </button>
          ))}
        </div>
        <div className={`${P}-status`} role="status" aria-live="polite">
          {status}
        </div>
      </div>
    </div>
  );
}
