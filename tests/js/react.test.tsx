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
      <EmojiPicker source={source} target={ref} inline={props.inline ?? true} store={props.store ?? memoryStore()} maxVersion={props.maxVersion ?? null} columns={props.columns ?? 8} onSelect={props.onSelect} />
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
    expect(screen.getByRole('status').textContent).toBe('1 results');
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
    await waitFor(() => expect(document.querySelector('section')?.getAttribute('data-laranail-emoji-section')).toBe('recent'));
  });

  it('moves with the arrow keys and picks with Enter', async () => {
    render(<Form columns={2} />);
    await ready();
    const field = screen.getByLabelText('message') as HTMLTextAreaElement;

    act(() => cells()[0].focus());
    fireEvent.keyDown(cells()[0], { key: 'ArrowRight' });
    expect(document.activeElement).toBe(cells()[1]);
    fireEvent.keyDown(cells()[1], { key: 'ArrowDown' });
    expect(document.activeElement).toBe(cells()[3]);
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

describe('the React build', () => {
  it('keeps React external and compiles out the vanilla auto-init', () => {
    const built = readFileSync(resolve(root, 'dist/react/index.js'), 'utf8');

    expect(built).toMatch(/from\s*["']react["']/);
    expect(built).not.toMatch(/MutationObserver/);
    expect(built).not.toMatch(/__laranailEmojiNoAutoInit/);
    expect(built).toMatch(/export\s*\{[^}]*\bEmojiPicker\b/);
  });
});
