import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { useRef, useState } from 'react';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payload } from './fixture.mjs';

(globalThis as { __laranailEmojiNoAutoInit?: boolean }).__laranailEmojiNoAutoInit = true;

const { EmojiPicker, StaticSource, memoryStore } = await import('../../resources/assets/react/index.ts');

const root = resolve(import.meta.dirname, '../..');

function Form(props: { onSelect?: (d: unknown) => void; inline?: boolean; store?: ReturnType<typeof memoryStore>; initial?: string; maxVersion?: string | null; columns?: number }) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const source = useRef(new StaticSource(payload())).current;

  return (
    <>
      <textarea aria-label="message" ref={ref} defaultValue={props.initial ?? ''} />
      <EmojiPicker source={source} target={ref} inline={props.inline ?? true} store={props.store ?? memoryStore()} maxVersion={props.maxVersion ?? null} columns={props.columns ?? 8} onSelect={props.onSelect} searchDelay={0} />
    </>
  );
}

function Controlled() {
  const ref = useRef<HTMLTextAreaElement>(null);
  const [value, setValue] = useState('hi ');
  const source = useRef(new StaticSource(payload())).current;

  return (
    <>
      <textarea aria-label="message" ref={ref} value={value} onChange={(e) => setValue(e.target.value)} />
      <output data-testid="state">{value}</output>
      <EmojiPicker source={source} target={ref} inline store={memoryStore()} maxVersion={null} />
    </>
  );
}

const ready = () => waitFor(() => expect(screen.getAllByRole('gridcell').length).toBeGreaterThan(0));
const cells = () => screen.getAllByRole('gridcell');

describe('EmojiPicker (React)', () => {
  beforeEach(() => localStorage.clear());
  afterEach(cleanup);

  it('inserts into a controlled textarea, so its state keeps the pick instead of writing the old value back', async () => {
    render(<Controlled />);
    await ready();

    fireEvent.click(cells()[0]);

    await waitFor(() => expect(screen.getByTestId('state').textContent).toBe('hi 😀'));
    expect((screen.getByLabelText('message') as HTMLTextAreaElement).value).toBe('hi 😀');
  });

  it('renders the groups as labelled grids of named cells, with the vanilla classes', async () => {
    const { container } = render(<Form />);
    await ready();

    expect(cells()).toHaveLength(7);
    expect(screen.getAllByRole('grid')).toHaveLength(4);
    expect(cells()[0]).toHaveProperty('textContent', '😀');
    expect(cells()[0].getAttribute('aria-label')).toBe('grinning face');
    expect(container.querySelector('.laranail-emoji-picker-panel')).not.toBeNull();
    expect(cells().filter((c) => c.tabIndex === 0)).toHaveLength(1);
  });

  it('appends to an unfocused field, inserts at a focused caret, and reports the pick', async () => {
    const seen: unknown[] = [];
    render(<Form initial="Hello " onSelect={(d) => seen.push(d)} />);
    await ready();
    const field = screen.getByLabelText('message') as HTMLTextAreaElement;
    field.selectionStart = field.selectionEnd = 0;

    fireEvent.click(cells()[0]);
    expect(field.value).toBe('Hello 😀');
    expect(seen[0]).toEqual({ emoji: '😀', hexcode: '1F600', name: 'grinning face', shortcode: 'grinning', custom: false });

    act(() => field.focus());
    field.setSelectionRange(0, 0);
    fireEvent.click(screen.getByLabelText('fusée'));
    expect(field.value).toBe('🚀Hello 😀');
  });

  it('searches with accents folded, announces the count, and keeps the tabs', async () => {
    render(<Form />);
    await ready();

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'fusee' } });
    expect(cells().map((c) => c.textContent)).toEqual(['🚀']);
    expect(screen.getByRole('status').textContent).toBe('1 result');
    expect(screen.getAllByRole('tab').length).toBeGreaterThan(0);

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } });
    expect(screen.getByRole('status').textContent).toBe('No emoji found');
  });

  it('applies and stores a skin tone, the same tone on every person', async () => {
    const store = memoryStore();
    render(<Form store={store} />);
    await ready();

    fireEvent.click(screen.getAllByRole('radio')[3]);
    expect(screen.getByLabelText('waving hand').textContent).toBe('👋🏽');
    expect(screen.getByLabelText('handshake').textContent).toBe('🤝🏽');
    expect(store.get('tone')).toBe(3);
  });

  it('shows recents first after a pick, once per base emoji', async () => {
    render(<Form />);
    await ready();

    fireEvent.click(screen.getByLabelText('fusée'));
    // Not under the pointer: the grid stays put until the pointer leaves the picker.
    expect(document.querySelector('section')?.getAttribute('data-laranail-emoji-section')).not.toBe('recent');

    fireEvent.pointerLeave(document.querySelector('.laranail-emoji-picker')!);
    await waitFor(() => expect(document.querySelector('section')?.getAttribute('data-laranail-emoji-section')).toBe('recent'));
  });

  it('moves with the arrow keys and picks with Enter', async () => {
    render(<Form columns={2} />);
    await ready();
    const field = screen.getByLabelText('message') as HTMLTextAreaElement;

    act(() => cells()[0].focus());
    fireEvent.keyDown(cells()[0], { key: 'ArrowRight' });
    expect(document.activeElement).toBe(cells()[1]);
    // A partial last row (😀 😂 / 🫠): ArrowDown keeps to the column instead of skipping into the next section.
    fireEvent.keyDown(cells()[1], { key: 'ArrowDown' });
    expect(document.activeElement).toBe(cells()[2]);
    fireEvent.keyDown(cells()[2], { key: 'ArrowDown' });
    expect(document.activeElement).toBe(cells()[3]);
    expect(cells().filter((c) => c.tabIndex === 0)).toEqual([cells()[3]]);
    fireEvent.keyDown(cells()[3], { key: 'End' });
    expect(document.activeElement).toBe(cells().at(-1));
    fireEvent.keyDown(cells().at(-1)!, { key: 'Home' });
    fireEvent.keyDown(cells()[0], { key: 'Enter' });
    expect(field.value).toBe('😀');
  });

  it('opens from its trigger as a dialog and closes on Escape back to it', async () => {
    render(<Form inline={false} />);
    const trigger = screen.getByRole('button', { name: 'Choose an emoji' });

    expect(screen.getByRole('dialog', { hidden: true }).hidden).toBe(true);
    fireEvent.click(trigger);
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    expect(screen.getByRole('dialog').hidden).toBe(false);
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
    expect(screen.getByRole('dialog', { hidden: true }).hidden).toBe(true);
    expect(document.activeElement).toBe(trigger);
  });

  it('hides emoji above a version cap, and inserts a custom emoji as its shortcode', async () => {
    render(<Form maxVersion="13.0" />);
    await ready();

    expect(screen.queryByLabelText('melting face')).toBeNull();
    fireEvent.click(screen.getByLabelText('Party parrot'));
    expect((screen.getByLabelText('message') as HTMLTextAreaElement).value).toBe(':partyparrot:');
  });

  it('shows the failure when the source rejects', async () => {
    const Failing = () => <EmojiPicker inline source={{ load: () => Promise.reject(new Error('down')) }} store={memoryStore()} />;
    render(<Failing />);

    await waitFor(() => expect(screen.getByRole('status').textContent).toBe('Emoji could not be loaded'));
  });

  it('never parses payload text as markup', async () => {
    const evil = payload();
    evil.groups[0].emoji[0].name = '<img src=x onerror=alert(1)>';
    const { container } = render(<EmojiPicker inline source={new StaticSource(evil)} store={memoryStore()} maxVersion={null} />);
    await ready();

    expect(container.querySelector('img[src="x"]')).toBeNull();
  });
});

describe('EmojiPicker (React) parity with the vanilla picker', () => {
  beforeEach(() => localStorage.clear());
  afterEach(cleanup);

  it('closes on an outside click and sends focus to the field after a pick', async () => {
    render(<Form inline={false} />);
    fireEvent.click(screen.getByRole('button', { name: 'Choose an emoji' }));
    await ready();

    fireEvent.click(cells()[0]);
    expect(document.activeElement).toBe(screen.getByLabelText('message'));
    expect(screen.getByRole('dialog', { hidden: true }).hidden).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Choose an emoji' }));
    fireEvent.pointerDown(document.body);
    expect(screen.getByRole('dialog', { hidden: true }).hidden).toBe(true);
  });

  it('lets Escape through when inline, and names the inline picker a group', async () => {
    render(<Form />);
    await ready();

    expect(screen.getByRole('group')).toBeTruthy();
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    cells()[0].dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
  });

  it('marks the selected tab and roves tabs and tones with the arrow keys', async () => {
    render(<Form />);
    await ready();
    const tabs = () => screen.getAllByRole('tab');
    const radios = () => screen.getAllByRole('radio');

    expect(tabs().map((t) => t.getAttribute('aria-selected'))).toEqual(['true', 'false', 'false', 'false']);
    act(() => tabs()[0].focus());
    fireEvent.keyDown(tabs()[0], { key: 'ArrowRight' });
    expect(document.activeElement).toBe(tabs()[1]);
    expect(tabs()[1].getAttribute('aria-selected')).toBe('true');

    act(() => radios()[0].focus());
    fireEvent.keyDown(radios()[0], { key: 'ArrowRight' });
    expect(radios()[1].getAttribute('aria-checked')).toBe('true');
    expect(document.activeElement).toBe(radios()[1]);
  });

  it('reads the stored tone again when userKey changes, so one user never sees the last one\'s', async () => {
    localStorage.setItem('laranail-emoji:ann:tone', '3');
    const source = new StaticSource(payload());
    const { rerender } = render(<EmojiPicker source={source} inline userKey="ann" maxVersion={null} />);
    await ready();
    await waitFor(() => expect(screen.getAllByRole('radio')[3].getAttribute('aria-checked')).toBe('true'));

    rerender(<EmojiPicker source={source} inline userKey="bob" maxVersion={null} />);
    await waitFor(() => expect(screen.getAllByRole('radio')[0].getAttribute('aria-checked')).toBe('true'));
  });

  it('renders on the server without touching storage', async () => {
    const { renderToString } = await import('react-dom/server');
    const spy = vi.spyOn(Storage.prototype, 'getItem');

    const html = renderToString(<EmojiPicker source={new StaticSource(payload())} inline />);

    expect(html).toContain('laranail-emoji-picker');
    expect(spy).not.toHaveBeenCalled();
    spy.mockRestore();
  });

  it('opens with the caret, and as a bottom sheet on a narrow screen that the backdrop dismisses', async () => {
    render(<Form inline={false} />);
    fireEvent.click(screen.getByRole('button', { name: 'Choose an emoji' }));
    await ready();

    expect(document.querySelector('.laranail-emoji-picker-arrow')).not.toBeNull();
    expect(document.querySelector('.laranail-emoji-picker-panel')!.getAttribute('data-placement')).toMatch(/-start$/);
    fireEvent.click(cells()[0]);
    cleanup();

    const matchMedia = vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({ matches: true, media: query, addEventListener() {}, removeEventListener() {} }) as unknown as MediaQueryList);

    try {
      render(<Form inline={false} />);
      fireEvent.click(screen.getByRole('button', { name: 'Choose an emoji' }));
      await ready();

      const backdrop = document.querySelector<HTMLElement>('.laranail-emoji-picker-backdrop')!;
      expect(document.querySelector('.laranail-emoji-picker')!.hasAttribute('data-sheet')).toBe(true);
      expect(backdrop.hidden).toBe(false);

      fireEvent.click(backdrop);
      await waitFor(() => expect(screen.getByRole('dialog', { hidden: true }).hidden).toBe(true));
    } finally {
      matchMedia.mockRestore();
    }
  });

  it('marks its bundle for the client', () => {
    expect(readFileSync(resolve(root, 'dist/react/index.js'), 'utf8')).toMatch(/^["']use client["'];/);
  });
});

describe('EmojiPicker (React) phase 3', () => {
  beforeEach(() => localStorage.clear());
  afterEach(cleanup);

  it('offers kaomoji in their own tab and inserts them as text; hides what features switch off', async () => {
    const data = { ...payload(), kaomoji: [{ slug: 'shrugging', label: 'Shrugging', items: [{ text: '¯\\_(ツ)_/¯', name: 'shrug' }] }] };
    const onSelect = vi.fn();
    const source = new StaticSource(data);

    function Kaomoji() {
      const ref = useRef<HTMLTextAreaElement>(null);

      return (
        <>
          <textarea aria-label="message" ref={ref} />
          <EmojiPicker source={source} target={ref} inline store={memoryStore()} maxVersion={null} searchDelay={0} onSelect={onSelect} features={{ skinTones: false }} />
        </>
      );
    }

    render(<Kaomoji />);
    await ready();

    expect(screen.getByRole('radiogroup', { hidden: true }).hidden).toBe(true);
    fireEvent.click(screen.getByRole('tab', { name: 'Kaomoji' }));
    fireEvent.click(screen.getByRole('gridcell', { name: 'shrug' }));

    expect((screen.getByLabelText('message') as HTMLTextAreaElement).value).toBe('¯\\_(ツ)_/¯');
    expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ kind: 'kaomoji', emoji: '¯\\_(ツ)_/¯' }));
  });

  it('draws icon tabs and a preview of the focused emoji', async () => {
    const { container } = render(<Form />);
    await ready();

    expect(container.querySelector('[role="tab"] svg path')).not.toBeNull();
    act(() => cells()[0].focus());
    expect(container.querySelector('.laranail-emoji-picker-preview-name')?.textContent).toBe('grinning face');
    expect(container.querySelector('.laranail-emoji-picker-preview-code')?.textContent).toBe(':grinning:');
  });
});

describe('the React build', () => {
  it('writes relative imports with .js, so its declarations resolve under moduleResolution node16/nodenext', () => {
    const files = ['index.ts', 'EmojiPicker.tsx', 'useEmojiPicker.ts'];
    const specifiers = files.flatMap((file) => [...readFileSync(resolve(root, 'resources/assets/react', file), 'utf8').matchAll(/from '(\.{1,2}\/[^']+)'/g)].map((m) => m[1]));

    expect(specifiers.length).toBeGreaterThanOrEqual(4);
    expect(specifiers.filter((spec) => !spec.endsWith('.js'))).toEqual([]);
  });

  it('keeps React external and compiles out the vanilla auto-init', () => {
    const built = readFileSync(resolve(root, 'dist/react/index.js'), 'utf8');

    expect(built).toMatch(/from\s*["']react["']/);
    expect(built).not.toMatch(/MutationObserver/);
    expect(built).not.toMatch(/__laranailEmojiNoAutoInit/);
    expect(built).toMatch(/export\s*\{[^}]*\bEmojiPicker\b/);
  });
});
