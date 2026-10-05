import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payload } from './fixture.mjs';

globalThis.__laranailEmojiNoAutoInit = true;

const module = await import('../../resources/assets/scripts/picker.ts');
const { Picker, StaticSource, ApiSource, memoryStore, localStorageStore, fold, charOf, withTone, search, sortItems, recordRecent, orderRecent, parseOptions, autoInit, byVersion, detectMaxVersion, buildSections, searchSections, capPayload, insertText, indexPayload, customCode, mountElement, readRecent, clampTone, clampColumns, searchCustom, rovingIndex, computePosition, parsePlacement, readFeatures, kindsOf, textSections, searchText, spySections, imageUrl, drawsImage, toneForms, IMAGE_RULES, parseShortcut, matchesShortcut, shortcutLabel, shortcutList, caretRect } = module;

const root = resolve(import.meta.dirname, '../..');
const flush = () => new Promise((r) => setTimeout(r, 0));

const mount = async (options = {}) => {
  document.body.replaceChildren();
  const host = document.createElement('div');
  const textarea = document.createElement('textarea');
  document.body.append(host, textarea);
  const picker = await Picker.create(host, { inline: true, store: memoryStore(), searchDelay: 0, ...options }).source(new StaticSource(payload())).target(textarea).mount();

  return { host, textarea, picker, cells: () => [...host.querySelectorAll('[role="gridcell"]')] };
};

describe('pure helpers', () => {
  it('folds case and diacritics', () => {
    expect(fold('  Fusée CAFÉ ')).toBe('fusee cafe');
  });

  it('turns hexcodes into characters and applies tones, the same tone on every person', () => {
    const [face, wave, handshake] = [payload().groups[0].emoji[0], payload().groups[1].emoji[0], payload().groups[1].emoji[1]];

    expect(charOf('1F44B-1F3FD')).toBe('👋🏽');
    expect(withTone(wave, 3)).toBe('1F44B-1F3FD');
    expect(withTone(handshake, 3)).toBe('1F91D-1F3FD');
    expect(withTone(face, 3)).toBe('1F600');
    expect(withTone(wave, 0)).toBe('1F44B');
  });

  it('searches names, shortcodes and keywords, exact and prefix first, every word required', () => {
    const items = payload().groups.flatMap((g) => g.emoji);
    const hex = (term) => search(items, term).map((i) => i.hexcode);

    expect(hex('fusee')).toEqual(['1F680']);
    expect(hex('face')).toEqual(['1F602', '1F600', '1FAE0']); // name prefix, keyword prefix, then anywhere
    expect(hex('grinning face')[0]).toBe('1F600');
    expect(hex('melting_face')).toEqual(['1FAE0']);
    expect(hex('face nope')).toEqual([]);
    expect(hex('   ')).toEqual([]);
  });

  it('sorts by name or newest version, and leaves CLDR order alone by default', () => {
    const items = payload().groups[0].emoji;

    expect(sortItems(items, 'default')).toBe(items);
    expect(sortItems(items, 'name').map((i) => i.name)).toEqual(['face with tears of joy', 'grinning face', 'melting face']);
    expect(sortItems(items, 'newest')[0].hexcode).toBe('1FAE0');
  });

  it('keeps recents once per base emoji, newest first, capped, and counts uses', () => {
    let list = [];
    list = recordRecent(list, '1F600', '1F600', 2, 1);
    list = recordRecent(list, '1F44B', '1F44B', 2, 2);
    list = recordRecent(list, '1F44B', '1F44B-1F3FD', 2, 3);

    expect(list).toEqual([
      { base: '1F44B', hexcode: '1F44B-1F3FD', count: 2, at: 3 },
      { base: '1F600', hexcode: '1F600', count: 1, at: 1 },
    ]);
    expect(recordRecent(list, '1F680', '1F680', 2, 4).map((e) => e.base)).toEqual(['1F680', '1F44B']);
    expect(orderRecent([{ base: 'a', count: 1, at: 9 }, { base: 'b', count: 5, at: 1 }], 'frequent')[0].base).toBe('b');
    expect(orderRecent([{ base: 'a', count: 1, at: 9 }, { base: 'b', count: 5, at: 1 }], 'recent')[0].base).toBe('a');
  });

  it('parses data-laranail-emoji-* options, typed, with safe defaults for junk', () => {
    const element = document.createElement('div');
    element.setAttribute('data-laranail-emoji-target', '#message');
    element.setAttribute('data-laranail-emoji-tone', '4');
    element.setAttribute('data-laranail-emoji-max-recent', 'lots');
    element.setAttribute('data-laranail-emoji-sort', 'evil');
    element.setAttribute('data-laranail-emoji-categories', 'recent, flags');
    element.setAttribute('data-laranail-emoji-close-on-select', 'false');
    element.setAttribute('data-laranail-emoji-strings', '{"search":"Chercher"');

    expect(parseOptions(element)).toMatchObject({
      target: '#message', tone: 4, maxRecent: 36, sort: 'default', categories: ['recent', 'flags'], closeOnSelect: false, inline: false, strings: {},
    });
  });
});

describe('shared state helpers', () => {
  it('builds Frequently used, the groups and Custom, limited to the categories asked for', () => {
    const recent = [{ base: '1F680', hexcode: '1F680', count: 1, at: 2 }, { base: 'GONE', hexcode: 'GONE', count: 9, at: 1 }];
    const all = buildSections(payload(), { recent, strings: { recent: 'Récents' } });

    expect(all.map((s) => s.slug)).toEqual(['recent', 'smileys_and_emotion', 'people_and_body', 'travel_and_places', 'custom']);
    expect(all[0]).toMatchObject({ label: 'Récents', items: [{ hexcode: '1F680' }] });
    expect(buildSections(payload(), { categories: ['custom'] }).map((s) => s.slug)).toEqual(['custom']);
    expect(searchSections(all, 'rocket').map((i) => i.hexcode)).toEqual(['1F680']);
    expect(indexPayload(payload()).get('1F91D').name).toBe('handshake');
  });

  it('caps a payload by version, by detection, or not at all', () => {
    const count = (p) => p.groups.flatMap((g) => g.emoji).length;

    expect(count(capPayload(payload(), '13.0'))).toBe(5);
    expect(count(capPayload(payload(), null))).toBe(6);
    expect(count(capPayload(payload(), ''))).toBe(6);
    expect(count(capPayload(payload(), 'auto', () => '0.6'))).toBe(3);
  });

  it('inserts at a known caret, appends otherwise, and announces input', () => {
    document.body.replaceChildren();
    const field = document.createElement('input');
    document.body.append(field);
    const onInput = vi.fn();
    field.addEventListener('input', onInput);
    field.value = 'ab';
    field.selectionStart = field.selectionEnd = 0;

    insertText(field, '🚀', false);
    expect(field.value).toBe('ab🚀');

    field.setSelectionRange(1, 1);
    insertText(field, '👋', true);
    expect(field.value).toBe('a👋b🚀');
    expect(onInput).toHaveBeenCalledTimes(2);
  });
});

describe('version cap', () => {
  it('compares dotted versions numerically', () => {
    expect(byVersion('15.1', '15.0')).toBeGreaterThan(0);
    expect(byVersion('9.0', '11.0')).toBeLessThan(0);
    expect(byVersion('14.0', '14.0')).toBe(0);
  });

  it('cannot tell without a canvas, and then hides nothing', async () => {
    expect(detectMaxVersion({ createElement: () => ({ getContext: () => null }) })).toBeNull();

    const { cells } = await mount();
    expect(cells().some((c) => c.getAttribute('aria-label') === 'melting face')).toBe(true);
  });

  it('names a kaomoji tab by its group, and shows a symbol or an emoji as itself', () => {
    expect(module.tabText({ slug: 'classic', label: 'Classic', text: true, items: [{ text: ':-)' }] })).toEqual({ text: 'Classic', label: true });
    expect(module.tabText({ slug: 'arrows', label: 'Arrows', text: true, items: [{ text: '←' }] })).toEqual({ text: '←', label: false });
    expect(module.tabText({ slug: 'smileys', label: 'Smileys', items: [{ hexcode: '1F600' }] })).toEqual({ text: '😀', label: false });
  });

  it('puts the result count in the results heading, and keeps the status line for screen readers', async () => {
    const { picker, host } = await mount();
    picker.searchInput.value = 'melt';
    picker.searchInput.dispatchEvent(new Event('input'));

    const heading = host.querySelector('.laranail-emoji-picker-heading');
    const status = host.querySelector('.laranail-emoji-picker-status');
    expect(heading.textContent).toBe(status.textContent);
    expect(heading.textContent).toMatch(/result/);
    expect(status.classList.contains('laranail-emoji-picker-status-results')).toBe(true);

    picker.searchInput.value = '';
    picker.searchInput.dispatchEvent(new Event('input'));
    expect(status.classList.contains('laranail-emoji-picker-status-results')).toBe(false);
  });

  it('hides emoji newer than an explicit cap, and searches only what is left', async () => {
    const { cells, picker } = await mount({ maxVersion: '13.0' });

    expect(cells().some((c) => c.getAttribute('aria-label') === 'melting face')).toBe(false);
    picker.searchInput.value = 'melt';
    picker.searchInput.dispatchEvent(new Event('input'));
    expect(cells()).toHaveLength(0);
  });

  it('detects the newest version a canvas draws in colour as one glyph', () => {
    // A fake canvas whose font knows emoji up to 14.0: newer ones draw as a black placeholder (macOS's
    // LastResort draws a different one per block, so no single "tofu" probe matches), sequences split.
    const known = new Set(['\u{1FAE0}', '\u{1F972}', '\u{1F600}']);
    const context = {
      font: '', textBaseline: '', fillStyle: '', last: '',
      clearRect() {}, fillText(text) { this.last = text; },
      getImageData() {
        return { data: this.last === '' ? [0, 0, 0, 0] : known.has(this.last) ? [250, 200, 40, 255] : [0, 0, 0, 255] };
      },
      measureText: (text) => ({ width: [...text].length > 2 ? 60 : 24 }),
    };
    const doc = { createElement: () => ({ getContext: () => context }) };

    expect(detectMaxVersion(doc)).toBe('14.0');
  });

  it('hides nothing when no sample draws in colour, or when the canvas adds noise', () => {
    const fake = (data) => ({ createElement: () => ({ getContext: () => ({ font: '', textBaseline: '', fillStyle: '', clearRect() {}, fillText() {}, getImageData: () => ({ data }), measureText: () => ({ width: 24 }) }) }) });

    // No colour emoji font: everything draws in the fill colour. The old probe fell back to 11.0 here.
    expect(detectMaxVersion(fake([0, 0, 0, 255]))).toBeNull();
    // Anti-fingerprinting noise colours even an empty canvas.
    expect(detectMaxVersion(fake([90, 10, 200, 255]))).toBeNull();
  });
});

describe('stores', () => {
  it('survive storage that throws, and namespace their keys', () => {
    const broken = { getItem: () => { throw new Error('denied'); }, setItem: () => { throw new Error('quota'); } };
    const original = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');
    Object.defineProperty(globalThis, 'localStorage', { value: broken, configurable: true });

    try {
      const store = localStorageStore('ns');
      store.set('tone', 3);
      expect(store.get('tone')).toBe(3);
    } finally {
      Object.defineProperty(globalThis, 'localStorage', original);
    }

    localStorageStore('app').set('tone', 2);
    expect(localStorage.getItem('app:tone')).toBe('2');
  });
});

describe('sources', () => {
  beforeEach(() => ApiSource.clear());

  afterEach(() => vi.restoreAllMocks());

  it('loads from the API with the locale, and fails loudly on an error status', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: payload() }), { status: 200 }));

    await expect(new ApiSource('/laranail/emojis/api/v1/').load('fr')).resolves.toMatchObject({ locale: 'en' });
    expect(fetch.mock.calls[0][0]).toBe('/laranail/emojis/api/v1/picker?locale=fr');

    fetch.mockResolvedValue(new Response('{}', { status: 500 }));
    await expect(new ApiSource('/x/picker').load()).rejects.toThrow('HTTP 500');
    fetch.mockRestore();
  });

  it('shares one request per URL and locale between pickers, and retries after a failure', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async () => new Response(JSON.stringify({ data: payload() }), { status: 200 }));

    await Promise.all([new ApiSource('/api/v1').load('en'), new ApiSource('/api/v1').load('en'), new ApiSource('/api/v1/').load('en')]);
    expect(fetch).toHaveBeenCalledTimes(1);

    await new ApiSource('/api/v1').load('fr');
    expect(fetch).toHaveBeenCalledTimes(2);

    ApiSource.clear();
    fetch.mockResolvedValueOnce(new Response('{}', { status: 503 }));
    await expect(new ApiSource('/api/v1').load('en')).rejects.toThrow('HTTP 503');
    await expect(new ApiSource('/api/v1').load('en')).resolves.toMatchObject({ locale: 'en' });
    fetch.mockRestore();
  });

  it('aborts one picker\'s load without failing the request the others share', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async () => new Response(JSON.stringify({ data: payload() }), { status: 200 }));
    const controller = new AbortController();
    const aborted = new ApiSource('/api/v1').load('en', controller.signal);
    const other = new ApiSource('/api/v1').load('en');

    controller.abort();
    await expect(aborted).rejects.toThrow('Aborted');
    await expect(other).resolves.toMatchObject({ locale: 'en' });
    fetch.mockRestore();
  });

  it('reads a JSON data block by id', async () => {
    const block = document.createElement('script');
    block.type = 'application/json';
    block.id = 'picker-data';
    block.textContent = JSON.stringify(payload());
    document.body.append(block);

    await expect(new StaticSource('picker-data').load()).resolves.toMatchObject({ dataset: 'test' });
    await expect(new StaticSource('missing').load()).rejects.toThrow('#missing');
  });
});

describe('Picker', () => {
  beforeEach(() => localStorage.clear());

  it('renders every group as a labelled grid of named cells, then the custom emoji', async () => {
    const { host, cells } = await mount();

    expect(cells()).toHaveLength(7);
    expect(host.querySelectorAll('[role="grid"]')).toHaveLength(4);
    expect(cells()[0].getAttribute('aria-label')).toBe('grinning face');
    expect(cells()[0].textContent).toBe('😀');
    expect(host.querySelector('[data-laranail-emoji-custom="partyparrot"] img').getAttribute('src')).toMatch(/^data:image\/png/);
    expect(host.querySelector('[role="tablist"]').children).toHaveLength(4);
  });

  it('never parses payload text as markup', async () => {
    document.body.replaceChildren();
    const host = document.createElement('div');
    document.body.append(host);
    const evil = payload();
    evil.groups[0].label = '<img src=x onerror=alert(1)>';
    evil.groups[0].emoji[0].name = '<b>bold</b>';

    await Picker.create(host, { inline: true, store: memoryStore() }).source(new StaticSource(evil)).mount();

    expect(host.querySelector('img[src="x"]')).toBeNull();
    expect(host.querySelector('b')).toBeNull();
    expect(host.textContent).toContain('<img src=x onerror=alert(1)>');
  });

  it('inserts at the caret, dispatches input and the select event, and records the pick', async () => {
    const { host, textarea, picker, cells } = await mount();
    const store = picker.store;
    const seen = [];
    const onInput = vi.fn();
    textarea.value = 'ab';
    textarea.focus();
    textarea.setSelectionRange(1, 1);
    textarea.addEventListener('input', onInput);
    host.addEventListener('laranail-emoji:select', (e) => seen.push(e.detail));
    picker.on('select', (d) => seen.push(d));

    cells()[0].click();

    expect(textarea.value).toBe('a😀b');
    expect(textarea.selectionStart).toBe(3);
    expect(onInput).toHaveBeenCalledOnce();
    expect(seen).toHaveLength(2);
    expect(seen[0]).toEqual({ emoji: '😀', hexcode: '1F600', name: 'grinning face', shortcode: 'grinning', custom: false });
    expect(store.get('recent')[0]).toMatchObject({ base: '1F600', count: 1 });
  });

  it('shows recents first, once per base, after a pick', async () => {
    const store = memoryStore();
    store.set('recent', [{ base: '1F680', hexcode: '1F680', count: 1, at: 1 }]);
    const { host } = await mount({ store });

    expect(host.querySelector('section').getAttribute('data-laranail-emoji-section')).toBe('recent');
    expect(host.querySelector('section [role="gridcell"]').textContent).toBe('🚀');
  });

  it('appends to a field that has never had focus, whose caret would read 0', async () => {
    const { textarea, cells } = await mount();
    textarea.value = 'Hello ';
    textarea.selectionStart = textarea.selectionEnd = 0; // what Chromium reports before any focus

    cells()[0].click();

    expect(textarea.value).toBe('Hello 😀');
  });

  it('inserts a custom emoji as its shortcode', async () => {
    const { textarea, host } = await mount();

    host.querySelector('[data-laranail-emoji-custom]').click();

    expect(textarea.value).toBe(':partyparrot:');
  });

  it('searches with diacritics folded and announces the result count', async () => {
    const { host, picker, cells } = await mount();
    picker.searchInput.value = 'fusee';
    picker.searchInput.dispatchEvent(new Event('input'));

    expect(cells().map((c) => c.textContent)).toEqual(['🚀']);
    expect(host.querySelector('[role="status"]').textContent).toBe('1 result');

    picker.searchInput.value = 'zzz';
    picker.searchInput.dispatchEvent(new Event('input'));
    expect(host.querySelector('[role="status"]').textContent).toBe('No emoji found');
  });

  it('applies and remembers a skin tone, on every person of a multi-person emoji', async () => {
    const { host, picker, cells } = await mount();
    const radios = [...host.querySelectorAll('[role="radio"]')];

    expect(radios).toHaveLength(6);
    radios[3].click();

    expect(cells().find((c) => c.getAttribute('aria-label') === 'waving hand').textContent).toBe('👋🏽');
    expect(cells().find((c) => c.getAttribute('aria-label') === 'handshake').textContent).toBe('🤝🏽');
    expect(picker.store.get('tone')).toBe(3);
    expect(host.querySelector('[role="radio"][aria-checked="true"]').getAttribute('aria-label')).toBe('Medium');
  });

  it('moves focus with the arrow keys, Home and End, and selects with Enter', async () => {
    const { textarea, picker, cells } = await mount({ columns: 2 });
    const key = (target, k) => target.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true }));

    picker.focusCell(cells()[0]);
    key(cells()[0], 'ArrowRight');
    expect(document.activeElement).toBe(cells()[1]);
    // 😀 😂 / 🫠 is a partial last row: ArrowDown from the second column lands on 🫠, not past it into the
    // next section, and keeps going down by column across the section boundary.
    key(cells()[1], 'ArrowDown');
    expect(document.activeElement).toBe(cells()[2]);
    key(cells()[2], 'ArrowDown');
    expect(document.activeElement).toBe(cells()[3]);
    key(cells()[3], 'ArrowUp');
    expect(document.activeElement).toBe(cells()[2]);
    key(cells()[2], 'PageDown');
    expect(document.activeElement.getAttribute('aria-label')).toBe('waving hand');
    key(document.activeElement, 'PageUp');
    expect(document.activeElement).toBe(cells()[0]);
    key(cells()[0], 'ArrowUp');
    expect(document.activeElement).toBe(picker.searchInput);
    picker.focusCell(cells()[3]);
    key(cells()[3], 'End');
    expect(document.activeElement).toBe(cells().at(-1));
    key(cells().at(-1), 'Home');
    expect(document.activeElement).toBe(cells()[0]);
    expect(cells().filter((c) => c.tabIndex === 0)).toHaveLength(1);
    key(cells()[0], 'Enter');
    expect(textarea.value).toBe('😀');
  });

  it('opens from its trigger with dialog semantics, and closes on Escape back to the trigger', async () => {
    const { host, picker } = await mount({ inline: false });
    const trigger = host.querySelector('[aria-haspopup="dialog"]');

    expect(picker.panel.hidden).toBe(true);
    trigger.click();
    expect(picker.panel.hidden).toBe(false);
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    picker.panel.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(picker.panel.hidden).toBe(true);
    expect(document.activeElement).toBe(trigger);
  });

  it('limits categories, sorts, and shows an error when the source fails', async () => {
    const { host } = await mount({ categories: ['travel_and_places'] });
    expect(host.querySelectorAll('section')).toHaveLength(1);

    document.body.replaceChildren();
    const failing = document.createElement('div');
    document.body.append(failing);
    const errors = [];
    await Picker.create(failing, { inline: true, store: memoryStore() }).source({ load: () => Promise.reject(new Error('down')) }).on('error', (e) => errors.push(e)).mount();

    expect(failing.querySelector('[role="status"]').textContent).toBe('Emoji could not be loaded');
    expect(errors).toHaveLength(1);
  });

  it('mounts once per element, and destroy() removes its DOM and listeners', async () => {
    const { host, picker } = await mount();

    // create() on an element with a live picker hands back that picker, not a detached copy.
    expect(Picker.create(host)).toBe(picker);
    expect(await Picker.create(host).mount()).toBe(picker);
    expect(host.querySelectorAll('.laranail-emoji-picker')).toHaveLength(1);
    picker.destroy();
    expect(host.children).toHaveLength(0);
  });

  it('auto-initialises from data attributes, including elements added later', async () => {
    document.body.replaceChildren();
    const block = document.createElement('script');
    block.type = 'application/json';
    block.id = 'auto-data';
    block.textContent = JSON.stringify(payload());
    document.body.append(block);

    const stop = autoInit(document);
    const later = document.createElement('div');
    later.setAttribute('data-laranail-emoji-picker', '');
    later.setAttribute('data-laranail-emoji-payload', 'auto-data');
    later.setAttribute('data-laranail-emoji-inline', '');
    document.body.append(later);
    await flush();
    await flush();

    expect(later.querySelectorAll('[role="gridcell"]').length).toBeGreaterThan(0);
    stop();
  });
});

describe('policy and version fidelity (hotfix track)', () => {
  const handshake = { emoji: '🤝', hexcode: '1F91D', name: 'handshake', shortcode: 'handshake', keywords: [], version: '3.0', skins: { 3: '1F91D-1F3FD', '3-3': '1F91D-1F3FD' }, skin_versions: '14.0' };
  const data = () => ({ groups: [{ slug: 'people_and_body', label: 'People', emoji: [structuredClone(handshake)] }] });

  it('caps toned forms on their own version, so an older platform falls back to the untoned emoji', () => {
    const capped = capPayload(data(), '13.0').groups[0].emoji[0];

    expect(capped.hexcode).toBe('1F91D');
    expect(capped.skins).toEqual({});
    expect(withTone(capped, 3)).toBe('1F91D');
    expect(withTone(capPayload(data(), '14.0').groups[0].emoji[0], 3)).toBe('1F91D-1F3FD');
  });

  it('caps per tone key when the variants differ in version', () => {
    const mixed = { ...structuredClone(handshake), skin_versions: { 3: '12.0', '3-3': '14.0' } };
    const capped = capPayload({ groups: [{ slug: 'p', label: 'P', emoji: [mixed] }] }, '13.0').groups[0].emoji[0];

    expect(Object.keys(capped.skins)).toEqual(['3']);
  });

  it('never offers a base the policy refuses: it falls back to a permitted toned form', () => {
    const only = { emoji: '👍', hexcode: '1F44D', name: 'thumbs up', shortcode: '+1', keywords: [], version: '0.6', skins: { 3: '1F44D-1F3FD' }, base: false };

    expect(withTone(only, 0)).toBe('1F44D-1F3FD');
    expect(withTone(only, 5)).toBe('1F44D-1F3FD');
    expect(withTone(only, 3)).toBe('1F44D-1F3FD');
    // Capping away its only permitted form drops it entirely.
    expect(capPayload({ groups: [{ slug: 'p', label: 'P', emoji: [{ ...only, skin_versions: '15.0' }] }] }, '13.0').groups[0].emoji).toEqual([]);
  });

  it('inserts a custom emoji between the configured delimiters', async () => {
    expect(customCode('parrot', null)).toBe(':parrot:');
    expect(customCode('parrot', { delimiters: ['{{', '}}'] })).toBe('{{parrot}}');

    document.body.replaceChildren();
    const host = document.createElement('div');
    const textarea = document.createElement('textarea');
    document.body.append(host, textarea);
    await Picker.create(host, { inline: true, store: memoryStore() }).source(new StaticSource({ ...payload(), delimiters: ['{{', '}}'] })).target(textarea).mount();
    host.querySelector('[data-laranail-emoji-custom="partyparrot"]').click();

    expect(textarea.value).toBe('{{partyparrot}}');
  });

  it('inserts below the value setter, so a framework tracking that setter sees the change', () => {
    const field = document.createElement('textarea');
    document.body.append(field);
    let tracked = '';
    // What React does: shadow the instance's value setter to remember the last value it wrote.
    const proto = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
    Object.defineProperty(field, 'value', { configurable: true, get() { return proto.get.call(this); }, set(v) { tracked = v; proto.set.call(this, v); } });
    field.value = 'hi';

    insertText(field, '😀', false);

    expect(field.value).toBe('hi😀');
    expect(tracked).toBe('hi'); // the tracker still holds the old value, so React fires onChange
  });

  it('inserts into a field without a selection API instead of throwing', () => {
    const email = document.createElement('input');
    email.type = 'email';
    document.body.append(email);
    email.value = 'a';
    const onInput = vi.fn();
    email.addEventListener('input', onInput);

    expect(() => insertText(email, '😀', false)).not.toThrow();
    expect(email.value).toBe('a😀');
    expect(onInput).toHaveBeenCalledOnce();
  });

  it('mounts every picker even when one names a target selector the browser rejects', async () => {
    document.body.replaceChildren();
    const bad = document.createElement('div');
    bad.setAttribute('data-laranail-emoji-picker', '');
    bad.setAttribute('data-laranail-emoji-target', '#3abc-input');
    bad.setAttribute('data-laranail-emoji-inline', '');
    const good = bad.cloneNode();
    good.setAttribute('data-laranail-emoji-target', "[id='3abc-input']");
    const field = document.createElement('textarea');
    field.id = '3abc-input';
    document.body.append(bad, good, field);

    const stop = autoInit(document);
    await flush();

    expect(bad.querySelector('.laranail-emoji-picker')).not.toBeNull();
    expect(good.querySelector('.laranail-emoji-picker')).not.toBeNull();
    stop();
  });

  it('mounts again when a morph strips a mounted picker out of its element', async () => {
    document.body.replaceChildren();
    const host = document.createElement('div');
    host.setAttribute('data-laranail-emoji-picker', '');
    host.setAttribute('data-laranail-emoji-inline', '');
    document.body.append(host);
    const stop = autoInit(document);
    await flush();
    const first = mountElement(host);

    host.replaceChildren(); // what a Livewire morph does to children it did not render
    await flush();

    expect(host.querySelector('.laranail-emoji-picker')).not.toBeNull();
    expect(mountElement(host)).not.toBe(first);
    stop();
  });
});

describe('interaction and lifecycle (phase 1)', () => {
  const key = (target, k, init = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init }));
  const popover = async (options = {}) => mount({ inline: false, ...options });

  it('closes on a click outside, leaving focus where the user put it', async () => {
    const { picker } = await popover();
    // Not focusable, so only the pointerdown listener (not focusout) can close it.
    const outside = document.createElement('div');
    document.body.append(outside);

    picker.open();
    const focused = document.activeElement;
    outside.dispatchEvent(new Event('pointerdown', { bubbles: true }));

    expect(picker.panel.hidden).toBe(true);
    expect(document.activeElement).toBe(focused);
  });

  it('closes when focus moves to something outside', async () => {
    const { picker } = await popover();
    const outside = document.createElement('button');
    document.body.append(outside);

    picker.open();
    picker.searchInput.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: outside }));

    expect(picker.panel.hidden).toBe(true);
  });

  it('keeps the column moving between rows of different sections', async () => {
    // Three columns: 😀 😂 🫠 / 👋 🤝 / 🚀. ArrowDown from 😂 (second column) lands on 🤝, not 👋.
    const { cells, picker } = await mount({ columns: 3 });

    picker.focusCell(cells()[1]);
    key(cells()[1], 'ArrowDown');
    expect(document.activeElement.getAttribute('aria-label')).toBe('handshake');
    key(document.activeElement, 'ArrowDown');
    expect(document.activeElement.getAttribute('aria-label')).toBe('fusée');
  });

  it('sends focus to the field after a pick, so typing carries on', async () => {
    const { picker, textarea, cells } = await popover();

    picker.open();
    cells()[0].click();

    expect(picker.panel.hidden).toBe(true);
    expect(document.activeElement).toBe(textarea);
  });

  it('lets Escape through when inline, and swallows it only when a popover closes', async () => {
    const inline = await mount();
    const inlineEvent = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    inline.cells()[0].dispatchEvent(inlineEvent);
    expect(inlineEvent.defaultPrevented).toBe(false);
    expect(inline.host.querySelector('[role="group"]')).not.toBeNull();

    const { picker, cells } = await popover();
    picker.open();
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    cells()[0].dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(picker.panel.hidden).toBe(true);
  });

  it('marks the selected tab, roves between tabs with the arrows, and gives no tab to an empty section', async () => {
    const { host } = await mount({ maxVersion: null, categories: ['smileys_and_emotion', 'people_and_body', 'travel_and_places'] });
    const tabs = () => [...host.querySelectorAll('[role="tab"]')];

    expect(tabs().map((t) => t.getAttribute('aria-selected'))).toEqual(['true', 'false', 'false']);
    expect(tabs().filter((t) => t.tabIndex === 0)).toHaveLength(1);
    expect(tabs()[0].getAttribute('aria-controls')).toBe(host.querySelector('section').id);

    tabs()[0].focus();
    key(tabs()[0], 'ArrowRight');
    expect(document.activeElement).toBe(tabs()[1]);
    expect(tabs()[1].getAttribute('aria-selected')).toBe('true');
    key(tabs()[1], 'End');
    expect(document.activeElement).toBe(tabs()[2]);

    const capped = await mount({ maxVersion: '0.6' });
    // Smileys keeps only 😂 (0.6); the capped picker still names only sections with emoji in them.
    expect([...capped.host.querySelectorAll('[role="tab"]')].every((t) => capped.host.querySelector(`section[data-laranail-emoji-section="${t.getAttribute('data-laranail-emoji-section')}"]`))).toBe(true);
  });

  it('roves the tone radios with the arrows, keeping focus on the radio', async () => {
    const { host, cells } = await mount();
    const radios = () => [...host.querySelectorAll('[role="radio"]')];

    radios()[0].focus();
    key(radios()[0], 'ArrowRight');
    key(document.activeElement, 'ArrowRight');
    key(document.activeElement, 'ArrowRight');

    expect(document.activeElement).toBe(radios()[3]);
    expect(radios()[3].getAttribute('aria-checked')).toBe('true');
    expect(cells().find((c) => c.getAttribute('aria-label') === 'waving hand').textContent).toBe('👋🏽');
  });

  it('clamps a tone or column count from anywhere', () => {
    expect([clampTone(9), clampTone('3'), clampTone(-1), clampTone('x'), clampTone(2.7)]).toEqual([0, 3, 0, 0, 2]);
    expect([clampColumns(0), clampColumns(-4), clampColumns('9'), clampColumns(99)]).toEqual([8, 8, 9, 24]);
    expect(rovingIndex(3, 2, 'ArrowRight')).toBe(0);
    expect(rovingIndex(3, 0, 'ArrowRight', true)).toBe(2);
  });

  it('survives a corrupt or foreign recents value in storage', async () => {
    expect(readRecent({})).toEqual([]);
    expect(readRecent([5, null, { base: '1F600', hexcode: '1F600', count: 2, at: 1 }])).toHaveLength(1);

    const store = memoryStore();
    store.set('recent', { future: 'schema' });
    const { cells } = await mount({ store });

    expect(cells().length).toBeGreaterThan(0);
    cells()[0].click();
    expect(readRecent(store.get('recent'))).toHaveLength(1);
  });

  it('keeps Frequently used still while the popover is open, then shows the pick, in the tone it was picked in', async () => {
    const { picker, host, cells } = await popover();

    picker.open();
    picker.skinTone(3);
    cells().find((c) => c.getAttribute('aria-label') === 'waving hand').click();
    expect(host.querySelector('[data-laranail-emoji-section="recent"]')).toBeNull();

    picker.skinTone(0);
    picker.open();
    const recent = host.querySelector('section[data-laranail-emoji-section="recent"] [role="gridcell"]');
    expect(recent.textContent).toBe('👋🏽');
  });

  it('finds :shortcode:, a pasted glyph, and custom emoji by name', async () => {
    const { picker, cells } = await mount();
    const find = (term) => {
      picker.searchInput.value = term;
      picker.searchInput.dispatchEvent(new Event('input'));

      return cells().map((c) => c.getAttribute('aria-label'));
    };

    expect(find(':rocket:')).toEqual(['fusée']);
    expect(find(':rock')).toEqual(['fusée']);
    expect(find('👋🏽')).toEqual(['waving hand']);
    expect(find('parrot')).toEqual(['Party parrot']);
    expect(searchCustom(payload().custom, 'party')).toHaveLength(1);
  });

  it('waits for typing to pause before searching', async () => {
    vi.useFakeTimers();

    try {
      const { picker, cells } = await mount({ searchDelay: 80 });
      const before = cells().length;

      picker.searchInput.value = 'rocket';
      picker.searchInput.dispatchEvent(new Event('input'));
      expect(cells()).toHaveLength(before);

      vi.advanceTimersByTime(80);
      expect(cells()).toHaveLength(1);
    } finally {
      vi.useRealTimers();
    }
  });

  it('respects maxlength, fires change, and inserts into contenteditable', () => {
    const input = document.createElement('input');
    input.maxLength = 2;
    input.value = 'ab';
    const onChange = vi.fn();
    input.addEventListener('change', onChange);

    expect(insertText(input, '😀', false)).toBe(false);
    expect(input.value).toBe('ab');

    input.maxLength = 10;
    expect(insertText(input, '😀', false)).toBe(true);
    expect(onChange).toHaveBeenCalledOnce();

    const editable = document.createElement('div');
    editable.contentEditable = 'true';
    editable.textContent = 'hi ';
    document.body.append(editable);

    expect(insertText(editable, '😀', false)).toBe(true);
    expect(editable.textContent).toBe('hi 😀');
  });

  it('draws only the newest load: a remount overtakes a slow one', async () => {
    document.body.replaceChildren();
    const host = document.createElement('div');
    document.body.append(host);
    let release;
    const slow = { load: () => new Promise((resolve) => (release = () => resolve({ groups: [{ slug: 'old', label: 'Old', emoji: [payload().groups[0].emoji[0]] }] }))) };
    const first = Picker.create(host, { inline: true, store: memoryStore() }).source(slow);
    const pending = first.mount();

    first.destroy();
    const second = await Picker.create(host, { inline: true, store: memoryStore() }).source(new StaticSource(payload())).mount();
    release();
    await pending;

    expect(host.querySelector('[data-laranail-emoji-section="old"]')).toBeNull();
    expect(second.isAttached()).toBe(true);
  });

  it('re-renders when an option changes after mount', async () => {
    const { picker, host } = await mount();

    picker.categories(['travel_and_places']);
    expect([...host.querySelectorAll('section')].map((x) => x.getAttribute('data-laranail-emoji-section'))).toEqual(['travel_and_places']);
  });

  it('shows a configured trigger', async () => {
    const { picker } = await popover({ trigger: '😺' });

    expect(picker.trigger.textContent).toBe('😺');
  });

  it('mirrors Left and Right under RTL', async () => {
    const { host, cells, picker } = await mount();
    host.setAttribute('dir', 'rtl');

    picker.focusCell(cells()[0]);
    key(cells()[0], 'ArrowLeft');
    expect(document.activeElement).toBe(cells()[1]);
  });

  it('merges headers into the API request and keeps a query string', async () => {
    ApiSource.clear();
    const calls = [];
    const spy = vi.spyOn(globalThis, 'fetch').mockImplementation(async (url, init) => {
      calls.push([url, init]);

      return new Response(JSON.stringify({ data: payload() }), { status: 200 });
    });

    await new ApiSource('/api/v1?tenant=7', { headers: { 'X-CSRF-TOKEN': 't' } }).load('fr');
    spy.mockRestore();

    expect(calls[0][0]).toBe('/api/v1/picker?tenant=7&locale=fr');
    const headers = new Headers(calls[0][1].headers);
    expect([headers.get('accept'), headers.get('x-csrf-token')]).toEqual(['application/json', 't']);
  });
});

describe('positioning (phase 2)', () => {
  const viewport = { x: 0, y: 0, width: 1000, height: 800 };
  const panel = { width: 300, height: 360 };
  const trigger = (x, y) => ({ x, y, width: 36, height: 36 });

  it('opens below, aligned to the trigger\'s start, with the caret on the trigger\'s centre', () => {
    const p = computePosition(trigger(100, 100), panel, viewport);

    expect([p.side, p.align, p.x, p.y]).toEqual(['bottom', 'start', 100, 144]);
    expect(p.arrow).toBe(18); // the trigger's centre, 18px in from the panel's left
    expect(p.hidden).toBe(false);
  });

  it('flips above when there is no room below, and keeps the room it has as the height cap', () => {
    const p = computePosition(trigger(100, 700), panel, viewport);

    expect(p.side).toBe('top');
    expect(p.y).toBe(700 - 8 - 360);
    expect(p.available).toBe(700 - 8 - 8);
  });

  it('stays on the side with more room when neither fits, capped to that room', () => {
    const p = computePosition(trigger(100, 300), { width: 300, height: 700 }, viewport);

    expect(p.side).toBe('bottom');
    expect(p.available).toBe(800 - 336 - 16);
  });

  it('shifts along the edge to stay on screen, and the caret still points at the trigger', () => {
    const p = computePosition(trigger(950, 100), panel, viewport);

    expect(p.x).toBe(1000 - 8 - 300);
    expect(p.arrow).toBe(950 + 18 - p.x);
  });

  it('keeps the caret clear of the rounded corners when the trigger is at the very edge', () => {
    const p = computePosition({ x: 0, y: 100, width: 4, height: 36 }, panel, viewport, { arrowPadding: 14 });

    expect(p.x).toBe(8);
    expect(p.arrow).toBe(14);
  });

  it('centres on top/bottom/start/end placements, and resolves start and end by reading direction', () => {
    expect(computePosition(trigger(400, 400), panel, viewport, { placement: 'top' }).align).toBe('center');
    expect(computePosition(trigger(400, 400), panel, viewport, { placement: 'start' }).side).toBe('left');
    expect(computePosition(trigger(400, 400), panel, viewport, { placement: 'start', rtl: true }).side).toBe('right');
    expect(computePosition(trigger(400, 400), panel, viewport, { placement: 'bottom-end' }).x).toBe(400 + 36 - 300);
    // In RTL, "start" alignment hangs from the trigger's right edge.
    expect(computePosition(trigger(400, 400), panel, viewport, { placement: 'bottom-start', rtl: true }).x).toBe(400 + 36 - 300);
    // Start with no room on the left flips to the right.
    expect(computePosition(trigger(20, 400), panel, viewport, { placement: 'start' }).side).toBe('right');
  });

  it('reports a trigger scrolled out of view', () => {
    expect(computePosition(trigger(100, -200), panel, viewport).hidden).toBe(true);
  });

  it('never shrinks the popover below two rows of emoji in a short viewport', async () => {
    HTMLElement.prototype.showPopover = () => {};
    HTMLElement.prototype.hidePopover = () => {};
    const height = Object.getOwnPropertyDescriptor(window, 'innerHeight');
    Object.defineProperty(window, 'innerHeight', { configurable: true, value: 420 });

    const { picker } = await mount({ inline: false });
    const rect = (left, top, width, height) => () => ({ left, top, width, height, x: left, y: top, right: left + width, bottom: top + height });
    // About 190px above the trigger and 190px below it: less than either side needs.
    picker.trigger.getBoundingClientRect = rect(100, 195, 30, 30);
    Object.defineProperty(picker.panel, 'offsetWidth', { configurable: true, get: () => 300 });
    Object.defineProperty(picker.panel, 'offsetHeight', { configurable: true, get: () => 416 });

    picker.open();

    expect(module.MIN_PANEL_HEIGHT).toBeGreaterThanOrEqual(300);
    expect(picker.panel.style.maxBlockSize).toBe(`${module.MIN_PANEL_HEIGHT}px`);
    // Too tall for either side, it is shifted over its own trigger: no caret pointing into itself.
    expect(picker.panel.querySelector('.laranail-emoji-picker-arrow').hidden).toBe(true);
    // And kept on screen: 340px from 8px down ends inside the 420px viewport, not past its bottom edge.
    expect(parseInt(picker.panel.style.top, 10) + module.MIN_PANEL_HEIGHT).toBeLessThanOrEqual(420 - 8);
    picker.close();

    // With room to spare, the panel keeps its own height: the floor only ever raises the limit.
    Object.defineProperty(window, 'innerHeight', { configurable: true, value: 1200 });
    picker.open();
    expect(parseInt(picker.panel.style.maxBlockSize, 10)).toBeGreaterThan(module.MIN_PANEL_HEIGHT);
    expect(picker.panel.querySelector('.laranail-emoji-picker-arrow').hidden).toBe(false);
    picker.close();

    if (height) Object.defineProperty(window, 'innerHeight', height);
    delete HTMLElement.prototype.showPopover;
    delete HTMLElement.prototype.hidePopover;
  });

  it('holds a box to its minimum size on screen, over the reference, without an arrow', () => {
    const short = { x: 0, y: 0, width: 800, height: 420 };
    const result = computePosition(trigger(100, 195), { width: 300, height: 416 }, short, { minSize: 340 });

    expect(result.y).toBeGreaterThanOrEqual(8);
    expect(result.y + 340).toBeLessThanOrEqual(420 - 8);
    expect(result.arrow).toBeNull();
    // With room, minSize changes nothing and the arrow stays.
    expect(computePosition(trigger(100, 100), { width: 300, height: 200 }, short, { minSize: 340 }).arrow).not.toBeNull();
  });

  it('accepts only known placements', () => {
    expect([parsePlacement('top-end'), parsePlacement('nope'), parsePlacement(null)]).toEqual(['top-end', 'auto', 'auto']);
  });

  it('puts the popover in the top layer with its placement, a caret, and a closed backdrop', async () => {
    // happy-dom has no Popover API; a browser with one gets popover="manual" and showPopover().
    const shown = vi.fn();
    HTMLElement.prototype.showPopover = shown;
    HTMLElement.prototype.hidePopover = () => {};

    const { picker, host } = await mount({ inline: false });
    const rect = (left, top, width, height) => () => ({ left, top, width, height, x: left, y: top, right: left + width, bottom: top + height });
    picker.trigger.getBoundingClientRect = rect(100, 100, 36, 36);
    // Drawn mid-animation at 96%: positioning must use the laid-out size, not this one.
    picker.panel.getBoundingClientRect = rect(0, 0, 288, 345);
    Object.defineProperty(picker.panel, 'offsetWidth', { configurable: true, get: () => 300 });
    Object.defineProperty(picker.panel, 'offsetHeight', { configurable: true, get: () => 360 });

    picker.open();

    expect(picker.panel.getAttribute('popover')).toBe('manual');
    expect([picker.panel.style.left, picker.panel.style.top]).toEqual(['100px', '144px']);
    expect(host.querySelector('.laranail-emoji-picker-arrow').style.left).toBe('18px');

    // At the right edge it shifts left by its laid-out width (300), not its scaled one (288).
    picker.trigger.getBoundingClientRect = rect(window.innerWidth - 30, 100, 24, 24);
    picker.close();
    picker.open();
    expect(picker.panel.style.left).toBe(`${window.innerWidth - 8 - 300}px`);
    expect(shown).toHaveBeenCalledTimes(2); // once per open
    delete HTMLElement.prototype.showPopover;
    delete HTMLElement.prototype.hidePopover;
    expect(picker.panel.getAttribute('data-placement')).toMatch(/^(bottom|top)-start$/);
    expect(host.querySelector('.laranail-emoji-picker-arrow').hidden).toBe(false);
    expect(host.querySelector('.laranail-emoji-picker-backdrop').hidden).toBe(true);
    expect(host.querySelector('.laranail-emoji-picker').hasAttribute('data-sheet')).toBe(false);

    picker.close();
    const noArrow = await mount({ inline: false, arrow: false });
    noArrow.picker.open();
    expect(noArrow.host.querySelector('.laranail-emoji-picker-arrow').hidden).toBe(true);
  });

  it('becomes a bottom sheet on a narrow screen: backdrop, page scroll locked, dismissed by the backdrop', async () => {
    const matchMedia = vi.spyOn(window, 'matchMedia').mockImplementation((query) => ({ matches: query.includes('640px'), media: query, addEventListener() {}, removeEventListener() {} }));

    try {
      const { picker, host } = await mount({ inline: false });
      document.body.style.overflow = 'auto';

      picker.open();

      const backdrop = host.querySelector('.laranail-emoji-picker-backdrop');
      expect(host.querySelector('.laranail-emoji-picker').hasAttribute('data-sheet')).toBe(true);
      expect(backdrop.hidden).toBe(false);
      expect(document.body.style.overflow).toBe('hidden');
      expect(document.activeElement).toBe(picker.panel); // not search: that would raise the keyboard

      picker.searchInput.dispatchEvent(new FocusEvent('focus'));
      expect(picker.panel.hasAttribute('data-expanded')).toBe(true);

      backdrop.click();
      expect(picker.panel.hidden).toBe(true);
      expect(document.body.style.overflow).toBe('auto');
      expect(backdrop.hidden).toBe(true);
    } finally {
      matchMedia.mockRestore();
    }
  });

  it('never becomes a sheet when the breakpoint is 0, and reads its options from attributes', async () => {
    const element = document.createElement('div');
    element.setAttribute('data-laranail-emoji-placement', 'top-end');
    element.setAttribute('data-laranail-emoji-offset', '12');
    element.setAttribute('data-laranail-emoji-arrow', 'false');
    element.setAttribute('data-laranail-emoji-sheet-breakpoint', '0');

    expect(parseOptions(element)).toMatchObject({ placement: 'top-end', offset: 12, arrow: false, sheetBreakpoint: 0 });
  });
});

describe('config, kinds and preview (phase 3)', () => {
  const withText = () => ({
    ...payload(),
    kaomoji: [{ slug: 'shrugging', label: 'Shrugging', items: [{ text: '¯\\_(ツ)_/¯', name: 'shrug' }, { text: '(╯°□°)╯︵ ┻━┻', name: 'table flip' }] }],
    symbols: [{ slug: 'arrows', label: 'Arrows', items: [{ char: '←', name: 'Leftwards arrow' }, { char: '→', name: 'Rightwards arrow' }] }],
  });
  const mountWith = async (data, options = {}) => {
    document.body.replaceChildren();
    const host = document.createElement('div');
    const textarea = document.createElement('textarea');
    document.body.append(host, textarea);
    const picker = await Picker.create(host, { inline: true, store: memoryStore(), searchDelay: 0, ...options }).source(new StaticSource(data)).target(textarea).mount();

    return { host, textarea, picker, cells: () => [...host.querySelectorAll('[role="gridcell"]')] };
  };

  it('reads features defensively: only booleans switch one off', () => {
    expect(readFeatures({ search: false, preview: 'no', bogus: false })).toEqual({ search: false, recents: true, skinTones: true, perPersonTones: true, setSwitcher: false, autocomplete: false, settings: true, preview: true, categoryTabs: true, custom: true });
    expect(readFeatures(null).search).toBe(true);
  });

  it('hides the parts switched off, and records no recents when recents are off', async () => {
    const store = memoryStore();
    const { host, picker, cells } = await mount({ store, features: { search: false, categoryTabs: false, skinTones: false, preview: false, recents: false, custom: false } });

    expect(picker.searchInput.hidden).toBe(true);
    expect(host.querySelector('.laranail-emoji-picker-tabs').hidden).toBe(true);
    expect(host.querySelector('.laranail-emoji-picker-tones').hidden).toBe(true);
    expect(host.querySelector('.laranail-emoji-picker-preview')).toBeNull();
    expect(host.querySelector('[data-laranail-emoji-custom]')).toBeNull();

    cells()[0].click();
    expect(store.get('recent')).toBeNull();
  });

  it('offers kaomoji and symbols in their own tabs when the payload carries them, and inserts them as text', async () => {
    expect(kindsOf(payload())).toEqual(['emoji']);
    expect(kindsOf(withText())).toEqual(['emoji', 'kaomoji', 'symbols']);
    expect(textSections(withText(), 'symbols')[0].items[0]).toEqual({ text: '←', name: 'Leftwards arrow' });

    const seen = [];
    const { host, textarea, picker, cells } = await mountWith(withText());
    picker.on('select', (d) => seen.push(d));
    const kinds = () => [...host.querySelectorAll('.laranail-emoji-picker-kinds [role="tab"]')];

    expect(kinds().map((k) => k.textContent)).toEqual(['Emoji', 'Kaomoji', 'Symbols']);

    kinds()[1].click();
    expect(kinds()[1].getAttribute('aria-selected')).toBe('true');
    expect(cells().map((c) => c.textContent)).toEqual(['¯\\_(ツ)_/¯', '(╯°□°)╯︵ ┻━┻']);

    cells()[0].click();
    expect(textarea.value).toBe('¯\\_(ツ)_/¯');
    expect(seen[0]).toMatchObject({ emoji: '¯\\_(ツ)_/¯', name: 'shrug', kind: 'kaomoji', hexcode: null });

    picker.searchInput.value = 'flip';
    picker.searchInput.dispatchEvent(new Event('input'));
    expect(cells().map((c) => c.getAttribute('aria-label'))).toEqual(['table flip']);
    expect(searchText(textSections(withText(), 'symbols')[0].items, 'right')).toHaveLength(1);
  });

  it('switches kinds with the arrow keys', async () => {
    const { host, cells } = await mountWith(withText());
    const kinds = () => [...host.querySelectorAll('.laranail-emoji-picker-kinds [role="tab"]')];

    kinds()[0].focus();
    kinds()[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));

    expect(document.activeElement.getAttribute('data-laranail-emoji-kind')).toBe('symbols');
    expect(cells()[0].textContent).toBe('←');
  });

  it('draws category tabs as outline icons, and shows the hovered emoji in the preview with its shortcode', async () => {
    const { host, cells } = await mount();

    expect(host.querySelector('[role="tab"][data-laranail-emoji-section="smileys_and_emotion"] svg path')).not.toBeNull();

    cells()[0].dispatchEvent(new Event('focusin', { bubbles: true }));
    const preview = host.querySelector('.laranail-emoji-picker-preview');

    expect(preview.getAttribute('aria-hidden')).toBe('true');
    expect(preview.querySelector('.laranail-emoji-picker-preview-glyph').textContent).toBe('😀');
    expect(preview.querySelector('.laranail-emoji-picker-preview-name').textContent).toBe('grinning face');
    expect(preview.querySelector('.laranail-emoji-picker-preview-code').textContent).toBe(':grinning:');
  });

  it('marks the tab of the section scrolled to', async () => {
    let fire;
    const original = globalThis.IntersectionObserver;
    globalThis.IntersectionObserver = class {
      constructor(callback) { fire = callback; }
      observe() {}
      disconnect() {}
    };

    try {
      const { host } = await mount();
      const section = host.querySelector('section[data-laranail-emoji-section="people_and_body"]');

      fire([{ target: section, isIntersecting: true }]);

      expect(host.querySelector('[role="tab"][data-laranail-emoji-section="people_and_body"]').getAttribute('aria-selected')).toBe('true');
      expect(host.querySelector('[role="tab"][data-laranail-emoji-section="smileys_and_emotion"]').getAttribute('aria-selected')).toBe('false');
      expect(spySections(document.createElement('div'), () => {})).toBeTypeOf('function');
    } finally {
      globalThis.IntersectionObserver = original;
    }
  });

  it('reads features from its attribute', () => {
    const element = document.createElement('div');
    element.setAttribute('data-laranail-emoji-features', '{"search":false}');

    expect(parseOptions(element).features.search).toBe(false);
    expect(parseOptions(element).features.preview).toBe(true);
  });
});

describe('image sets, the full catalogue and per-person tones (phase 4)', () => {
  const fixture = JSON.parse(readFileSync(resolve(root, 'tests/js/fixtures/image-urls.json'), 'utf8'));
  const twemoji = { set: 'twemoji', licence: 'CC-BY-4.0', base: 'https://cdn.example/twemoji', rule: 'twemoji', suffix: '.svg', missing: ['1FAE0'] };
  const noto = { set: 'noto', licence: 'Apache-2.0', base: 'https://cdn.example/noto', rule: 'noto', suffix: '.svg', missing: [] };

  it('builds every image URL exactly as the server does', () => {
    let compared = 0;

    for (const [name, urls] of Object.entries(fixture.urls)) {
      for (const [hexcode, url] of Object.entries(urls)) {
        expect(imageUrl(fixture.sets[name], hexcode), `${name} ${hexcode}`).toBe(url);
        compared++;
      }
    }

    // A fixture that stopped carrying samples would pass trivially.
    expect(compared).toBeGreaterThanOrEqual(50);
    expect(Object.keys(IMAGE_RULES).sort()).toEqual(['joypixels', 'noto', 'openmoji', 'twemoji']);
  });

  it('reads per-emoji paths and URLs for sets without a rule', () => {
    expect(imageUrl({ set: 'fluent', licence: 'MIT', base: 'https://x', paths: { '1F600': 'Grinning%20face/a.svg' } }, '1F600')).toBe('https://x/Grinning%20face/a.svg');
    expect(imageUrl({ set: 'mine', licence: '', urls: { '1F600': 'https://y/1.png' } }, '1F600')).toBe('https://y/1.png');
    expect(imageUrl({ set: 'mine', licence: '', urls: {} }, '1F600')).toBeNull();
    expect(imageUrl(null, '1F600')).toBeNull();
  });

  it('keeps what the device cannot draw when the set can, marked to be drawn as an image', () => {
    const data = { ...payload(), images: twemoji };
    data.groups[1].emoji[1].skin_versions = '14.0'; // 🤝's tones
    const capped = capPayload(data, '13.0', undefined, { set: twemoji });
    const all = capped.groups.flatMap((g) => g.emoji);
    const handshake = all.find((i) => i.hexcode === '1F91D');

    // 🫠 (14.0) is beyond the cap, but Twemoji lacks it here: still dropped.
    expect(all.some((i) => i.hexcode === '1FAE0')).toBe(false);
    // 🤝's tones are beyond the cap and Twemoji has them: kept, drawn as images.
    expect(handshake.imageSkins).toEqual(['3-3']);
    expect(drawsImage(handshake, '1F91D-1F3FD', 'auto', twemoji)).toBe('https://cdn.example/twemoji/1f91d-1f3fd.svg');
    expect(drawsImage(handshake, '1F91D', 'auto', twemoji)).toBeNull();
    expect(drawsImage(handshake, '1F91D', 'image', twemoji)).toBe('https://cdn.example/twemoji/1f91d.svg');
    expect(drawsImage(handshake, '1F91D-1F3FD', 'native', twemoji)).toBeNull();

    const withMelt = capPayload({ ...payload(), images: noto }, '13.0', undefined, { set: noto }).groups[0].emoji.find((i) => i.hexcode === '1FAE0');
    expect(withMelt.draw).toBe('image');
  });

  it('draws flags as images where the OS draws letters', () => {
    const data = { ...payload(), groups: [...payload().groups, { slug: 'flags', label: 'Flags', emoji: [{ emoji: '🇺🇸', hexcode: '1F1FA-1F1F8', name: 'flag: United States', shortcode: 'us', keywords: [], version: '2.0', skins: {} }] }] };
    const flag = capPayload(data, null, undefined, { set: twemoji, flags: false }).groups.at(-1).emoji[0];

    expect(flag.draw).toBe('image');
    expect(capPayload(data, null, undefined, { set: twemoji, flags: true }).groups.at(-1).emoji[0].draw).toBeUndefined();
  });

  it('draws images in the grid where the device cannot, and falls back to the glyph when one fails', async () => {
    const data = { ...payload(), images: noto };
    document.body.replaceChildren();
    const host = document.createElement('div');
    document.body.append(host);
    await Picker.create(host, { inline: true, store: memoryStore(), maxVersion: '13.0', searchDelay: 0 }).source(new StaticSource(data)).mount();
    const melt = host.querySelector('[aria-label="melting face"]');
    const grin = host.querySelector('[aria-label="grinning face"]');

    expect(melt.querySelector('img').getAttribute('src')).toBe('https://cdn.example/noto/emoji_u1fae0.svg');
    expect(melt.querySelector('img').getAttribute('alt')).toBe('');
    expect(grin.querySelector('img')).toBeNull();

    melt.querySelector('img').dispatchEvent(new Event('error'));
    expect(melt.querySelector('img')).toBeNull();
    expect(melt.textContent).toBe('🫠');
  });

  it('lets the user switch between native emoji and the payload\'s sets, and remembers the choice', async () => {
    const store = memoryStore();
    const data = { ...payload(), images: twemoji, imageSets: [twemoji, noto] };
    document.body.replaceChildren();
    const host = document.createElement('div');
    document.body.append(host);
    await Picker.create(host, { inline: true, store, maxVersion: null, searchDelay: 0, features: { setSwitcher: true } }).source(new StaticSource(data)).mount();
    const select = host.querySelector('.laranail-emoji-picker-set');

    expect([...select.options].map((o) => o.textContent)).toEqual(['Native', 'Twemoji', 'Noto']);
    expect(host.querySelector('[aria-label="grinning face"] img')).toBeNull();

    select.value = 'noto';
    select.dispatchEvent(new Event('change'));

    expect(store.get('set')).toBe('noto');
    expect(host.querySelector('[aria-label="grinning face"] img').getAttribute('src')).toBe('https://cdn.example/noto/emoji_u1f600.svg');
  });

  it('knows each emoji\'s toned forms, one person or two', () => {
    const wave = payload().groups[1].emoji[0];
    const handshake = { ...payload().groups[1].emoji[1], skins: { 3: '1F91D-1F3FD', '3-5': '1FAF1-1F3FD-200D-1FAF2-1F3FF' } };

    expect(toneForms(wave).people).toBe(1);
    expect(toneForms(wave).form(0)).toBe('1F44B');
    expect(toneForms(wave).form(4)).toBe('1F44B-1F3FE');
    expect(toneForms(handshake).people).toBe(2);
    expect(toneForms(handshake).form(3, 3)).toBe('1F91D-1F3FD');
    expect(toneForms(handshake).form(3, 5)).toBe('1FAF1-1F3FD-200D-1FAF2-1F3FF');
    expect(toneForms(handshake).form(1, 2)).toBeNull();
    expect(toneForms(payload().groups[0].emoji[0]).people).toBe(0);
  });

  it('opens a tone menu on right click: one tone for this pick, recorded exactly', async () => {
    const store = memoryStore();
    const { host, textarea } = await mount({ store });
    const wave = host.querySelector('[aria-label="waving hand"]');

    expect(host.querySelector('[aria-label="grinning face"]').dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }))).toBe(true); // no tones: the browser's menu
    expect(wave.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }))).toBe(false);

    const menu = host.querySelector('.laranail-emoji-picker-tonemenu');
    expect(menu.getAttribute('role')).toBe('dialog');
    expect([...menu.querySelectorAll('[data-laranail-emoji-hexcode]')].map((b) => b.textContent)).toEqual(['👋', '👋🏻', '👋🏼', '👋🏽', '👋🏾', '👋🏿']);

    menu.querySelector('[data-laranail-emoji-hexcode="1F44B-1F3FE"]').click();

    expect(textarea.value).toBe('👋🏾');
    expect(readRecent(store.get('recent'))[0]).toMatchObject({ base: '1F44B', hexcode: '1F44B-1F3FE' });
    expect(host.querySelector('.laranail-emoji-picker-tonemenu')).toBeNull();
  });

  it('gives each person in 🤝 a tone, previews the result, and closes on Escape back to the emoji', async () => {
    const data = payload();
    data.groups[1].emoji[1].skins = { 1: '1F91D-1F3FB', 3: '1F91D-1F3FD', '3-5': '1FAF1-1F3FD-200D-1FAF2-1F3FF', '1-3': '1FAF1-1F3FB-200D-1FAF2-1F3FD' };
    document.body.replaceChildren();
    const host = document.createElement('div');
    const textarea = document.createElement('textarea');
    document.body.append(host, textarea);
    await Picker.create(host, { inline: true, store: memoryStore(), maxVersion: null, searchDelay: 0 }).source(new StaticSource(data)).target(textarea).mount();
    const handshake = host.querySelector('[aria-label="handshake"]');

    handshake.dispatchEvent(new KeyboardEvent('keydown', { key: 'F10', shiftKey: true, bubbles: true, cancelable: true }));
    let menu = host.querySelector('.laranail-emoji-picker-tonemenu');
    const rows = menu.querySelectorAll('[role="radiogroup"]');

    expect(rows).toHaveLength(2);
    rows[0].querySelector('[data-laranail-emoji-tone="3"]').click();
    rows[1].querySelector('[data-laranail-emoji-tone="5"]').click();

    const result = menu.querySelector('.laranail-emoji-picker-tonemenu-result');
    expect(result.dataset.hexcode).toBe('1FAF1-1F3FD-200D-1FAF2-1F3FF');
    rows[1].querySelector('[data-laranail-emoji-tone="2"]').click();
    expect(result.disabled).toBe(true); // 3-2 is not offered

    menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(host.querySelector('.laranail-emoji-picker-tonemenu')).toBeNull();
    expect(document.activeElement).toBe(handshake);

    handshake.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }));
    menu = host.querySelector('.laranail-emoji-picker-tonemenu');
    menu.querySelectorAll('[role="radiogroup"]')[0].querySelector('[data-laranail-emoji-tone="1"]').click();
    menu.querySelectorAll('[role="radiogroup"]')[1].querySelector('[data-laranail-emoji-tone="3"]').click();
    menu.querySelector('.laranail-emoji-picker-tonemenu-result').click();
    expect(textarea.value).toBe('🫱🏻‍🫲🏽');
  });

  it('opens the tone menu on a long press, without picking the emoji it was held on', async () => {
    vi.useFakeTimers();

    try {
      const { host, textarea } = await mount();
      const wave = host.querySelector('[aria-label="waving hand"]');

      wave.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, pointerType: 'touch', clientX: 5, clientY: 5 }));
      vi.advanceTimersByTime(500);
      expect(host.querySelector('.laranail-emoji-picker-tonemenu')).not.toBeNull();

      wave.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, pointerType: 'touch' }));
      wave.click();
      expect(textarea.value).toBe('');
    } finally {
      vi.useRealTimers();
    }
  });
});

describe('light and dark, and loading fast', () => {
  const big = () => ({
    groups: ['smileys_and_emotion', 'people_and_body', 'objects'].map((slug, g) => ({
      slug,
      label: slug,
      emoji: Array.from({ length: 150 }, (_, i) => {
        const hexcode = (0x1f300 + g * 150 + i).toString(16).toUpperCase();

        return { emoji: '', hexcode, name: `e${g}-${i}`, shortcode: null, keywords: [], version: '1.0', skins: {} };
      }),
    })),
  });
  const mountBig = async (options = {}) => {
    document.body.replaceChildren();
    const host = document.createElement('div');
    document.body.append(host);
    const picker = await Picker.create(host, { inline: true, store: memoryStore(), maxVersion: null, searchDelay: 0, ...options }).source(new StaticSource(big())).mount();

    return { host, picker, cells: () => host.querySelectorAll('[role="gridcell"]').length };
  };
  const idle = () => new Promise((r) => setTimeout(r, 30));

  it('fixes its theme on request, and follows the page again on auto', async () => {
    const { picker, host } = await mount();
    const root = host.querySelector('.laranail-emoji-picker');

    expect(root.hasAttribute('data-theme')).toBe(false);
    picker.theme('dark');
    expect(root.getAttribute('data-theme')).toBe('dark');
    picker.theme('auto');
    expect(root.hasAttribute('data-theme')).toBe(false);

    const fixed = await mount({ theme: 'light' });
    expect(fixed.host.querySelector('.laranail-emoji-picker').getAttribute('data-theme')).toBe('light');
  });

  it('parses an embedded payload once, however many pickers read it', async () => {
    document.body.replaceChildren();
    const block = document.createElement('script');
    block.type = 'application/json';
    block.id = 'shared-payload';
    block.textContent = JSON.stringify(payload());
    document.body.append(block);
    const parse = vi.spyOn(JSON, 'parse');

    try {
      const loads = await Promise.all([1, 2, 3].map(() => new StaticSource('shared-payload').load()));

      expect(parse.mock.calls.filter(([text]) => typeof text === 'string' && text.includes('grinning'))).toHaveLength(1);
      expect(loads[0]).toBe(loads[2]);
    } finally {
      parse.mockRestore();
    }
  });

  it('builds a popover\'s grid the first time it opens, not on page load', async () => {
    const { picker, host } = await mount({ inline: false });

    expect(host.querySelectorAll('[role="gridcell"]')).toHaveLength(0);
    picker.open();
    expect(host.querySelectorAll('[role="gridcell"]').length).toBeGreaterThan(0);
  });

  it('draws about a screenful first and the rest in idle time', async () => {
    const { cells } = await mountBig();

    expect(cells()).toBeGreaterThanOrEqual(150);
    expect(cells()).toBeLessThan(450);

    await idle();
    await idle();
    expect(cells()).toBe(450);
  });

  it('draws everything at once when a tab or a key needs a section not drawn yet', async () => {
    const { host, cells } = await mountBig();

    host.querySelector('[role="tab"][data-laranail-emoji-section="objects"]').click();
    expect(cells()).toBe(450);

    const again = await mountBig();
    const first = again.host.querySelector('[role="gridcell"]');
    first.focus();
    first.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
    expect(document.activeElement.getAttribute('aria-label')).toBe('e2-149');
  });

  // Guards the queue being replaced on each render; the token check in renderBody is a second line of
  // defence that this cannot tell apart from it.
  it('shows only search results when a search starts while sections are still being drawn', async () => {
    const { picker, host } = await mountBig();

    picker.searchInput.value = 'e2-14';
    picker.searchInput.dispatchEvent(new Event('input'));
    await idle();
    await idle();

    expect([...host.querySelectorAll('[role="gridcell"]')].every((c) => c.getAttribute('aria-label').startsWith('e2-14'))).toBe(true);
  });
});

describe('shortcuts, settings and autocomplete', () => {
  const key = (target, k, init = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init }));
  const typeInto = (field, text) => {
    field.focus();
    field.value = text;
    field.setSelectionRange(text.length, text.length);
    field.dispatchEvent(new Event('input', { bubbles: true }));
  };

  it('parses, matches and writes shortcuts, Mod being ⌘ on Apple and Ctrl elsewhere', () => {
    const open = parseShortcut('Mod+Shift+.');

    expect(open).toMatchObject({ code: 'Period', mod: true, shift: true, alt: false });
    expect(matchesShortcut({ code: 'Period', ctrlKey: true, metaKey: false, altKey: false, shiftKey: true }, open, false)).toBe(true);
    expect(matchesShortcut({ code: 'Period', ctrlKey: false, metaKey: true, altKey: false, shiftKey: true }, open, true)).toBe(true);
    expect(matchesShortcut({ code: 'Period', ctrlKey: true, metaKey: false, altKey: false, shiftKey: false }, open, false)).toBe(false);
    expect(shortcutLabel(open, true)).toBe('⇧⌘.');
    expect(shortcutLabel(open, false)).toBe('Ctrl+Shift+.');
    expect(parseShortcut('Alt+E').code).toBe('KeyE');
    expect([parseShortcut(''), parseShortcut(null), parseShortcut('Hyper+E'), parseShortcut('Mod+Ü')]).toEqual([null, null, null, null]);
    expect(shortcutList({}, open, false).map(([, keys]) => keys)).toContain('Ctrl+Shift+.');
  });

  it('opens from its field with the shortcut, and takes focus in an inline picker', async () => {
    const { picker, textarea } = await mount({ inline: false, shortcut: 'Ctrl+E' });

    textarea.focus();
    key(textarea, 'e', { code: 'KeyE', ctrlKey: true });
    expect(picker.panel.hidden).toBe(false);

    const inline = await mount({ shortcut: 'Ctrl+E' });
    key(inline.textarea, 'e', { code: 'KeyE', ctrlKey: true });
    expect(document.activeElement).toBe(inline.picker.searchInput);

    const off = document.createElement('div');
    off.setAttribute('data-laranail-emoji-shortcut', '');
    expect(parseOptions(off).shortcut).toBeNull();
    expect(parseOptions(document.createElement('div')).shortcut).toBe('Mod+Shift+.');
  });

  it('answers / for search, Alt+number for a category, and ? for the shortcut list', async () => {
    const { picker, host, cells } = await mount();

    picker.focusCell(cells()[0]);
    key(cells()[0], '/');
    expect(document.activeElement).toBe(picker.searchInput);

    picker.focusCell(cells()[0]);
    key(cells()[0], '2', { code: 'Digit2', altKey: true });
    expect(document.activeElement.getAttribute('data-laranail-emoji-section')).toBe('people_and_body');
    expect(document.activeElement.getAttribute('aria-selected')).toBe('true');

    picker.focusCell(cells()[0]);
    key(cells()[0], '?');
    const menu = host.querySelector('.laranail-emoji-picker-settings');
    expect(menu).not.toBeNull();
    expect(document.activeElement.tagName).toBe('DL');

    // Typing in the search field keeps / and ? as text.
    picker.searchInput.focus();
    expect(key(picker.searchInput, '/')).toBe(true);
  });

  it('keeps a settings menu behind the gear: theme, clearing recents, and the shortcuts', async () => {
    const store = memoryStore();
    const { host, cells } = await mount({ store });
    const gear = host.querySelector('.laranail-emoji-picker-gear');
    const root = host.querySelector('.laranail-emoji-picker');

    cells()[0].click(); // a recent to clear
    gear.click();
    let menu = host.querySelector('.laranail-emoji-picker-settings');

    expect(gear.getAttribute('aria-expanded')).toBe('true');
    expect(menu.getAttribute('role')).toBe('dialog');
    menu.querySelector('[data-laranail-emoji-theme="dark"]').click();
    expect(root.getAttribute('data-theme')).toBe('dark');
    expect(store.get('theme')).toBe('dark');

    const clear = menu.querySelector('.laranail-emoji-picker-settings-clear');
    expect(clear.disabled).toBe(false);
    clear.click();
    expect(readRecent(store.get('recent'))).toEqual([]);
    expect(clear.disabled).toBe(true);
    expect(menu.querySelectorAll('.laranail-emoji-picker-shortcuts kbd').length).toBeGreaterThanOrEqual(6);
    // "← ↑ → ↓ · PgUp PgDn · Home End" is three chips, so a narrow menu wraps between them, not through one.
    const move = [...menu.querySelectorAll('.laranail-emoji-picker-shortcuts dd')].find((dd) => dd.textContent.includes('PgUp'));
    expect([...move.querySelectorAll('kbd')].map((k) => k.textContent)).toEqual(['← ↑ → ↓', 'PgUp PgDn', 'Home End']);

    key(menu, 'Escape');
    expect(host.querySelector('.laranail-emoji-picker-settings')).toBeNull();
    expect(document.activeElement).toBe(gear);
    expect(gear.getAttribute('aria-expanded')).toBe('false');

    // The remembered theme comes back on the next mount; a theme the page fixed hides the choice.
    const again = await mount({ store });
    expect(again.host.querySelector('.laranail-emoji-picker').getAttribute('data-theme')).toBe('dark');
    const fixed = await mount({ store, theme: 'light' });
    fixed.host.querySelector('.laranail-emoji-picker-gear').click();
    menu = fixed.host.querySelector('.laranail-emoji-picker-settings');
    expect(menu.querySelector('[role="radiogroup"]')).toBeNull();
    expect(fixed.host.querySelector('.laranail-emoji-picker').getAttribute('data-theme')).toBe('light');
  });

  it('closes the settings and tone menus on a press outside them', async () => {
    const { host } = await mount();

    host.querySelector('.laranail-emoji-picker-gear').click();
    host.querySelector('[aria-label="waving hand"]').dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 5)); // the dismiss listener is armed after the opening press
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));

    expect(host.querySelector('.laranail-emoji-picker-settings')).toBeNull();
    expect(host.querySelector('.laranail-emoji-picker-tonemenu')).toBeNull();
  });

  it('leaves the gear out when settings are switched off', async () => {
    const { host } = await mount({ features: { settings: false } });

    expect(host.querySelector('.laranail-emoji-picker-gear')).toBeNull();
  });

  it('suggests nothing as a shortcode is typed unless autocomplete is on', async () => {
    const { host, textarea } = await mount();

    typeInto(textarea, 'hi :gri');
    expect(host.querySelector('.laranail-emoji-picker-suggest')).toBeNull();
  });

  it('suggests emoji beside the caret as a shortcode is typed, and inserts the pick in place of the code', async () => {
    const seen = [];
    const store = memoryStore();
    const { host, textarea, picker } = await mount({ store, features: { autocomplete: true } });
    picker.on('select', (d) => seen.push(d));

    typeInto(textarea, 'hi :gr');
    const list = host.querySelector('.laranail-emoji-picker-suggest');
    const options = () => [...list.querySelectorAll('[role="option"]')];

    expect(list.getAttribute('role')).toBe('listbox');
    expect(options()[0].getAttribute('aria-label')).toBe('grinning face');
    expect(textarea.getAttribute('aria-expanded')).toBe('true');
    expect(textarea.getAttribute('aria-controls')).toBe(list.id);
    expect(textarea.getAttribute('aria-activedescendant')).toBe(options()[0].id);

    key(textarea, 'Enter');

    expect(textarea.value).toBe('hi 😀');
    expect(host.querySelector('.laranail-emoji-picker-suggest')).toBeNull();
    expect(textarea.hasAttribute('aria-expanded')).toBe(false);
    expect(seen[0]).toMatchObject({ emoji: '😀', hexcode: '1F600' });
    expect(readRecent(store.get('recent'))[0].base).toBe('1F600');
  });

  it('moves with the arrows, dismisses on Escape until the next code, and suggests custom emoji', async () => {
    const { host, textarea } = await mount({ features: { autocomplete: true } });

    typeInto(textarea, ':party');
    expect(host.querySelector('[role="option"]').getAttribute('aria-label')).toBe('Party parrot');
    key(textarea, 'Enter');
    expect(textarea.value).toBe(':partyparrot:');

    typeInto(textarea, 'x :wav');
    key(textarea, 'Escape');
    expect(host.querySelector('.laranail-emoji-picker-suggest')).toBeNull();
    typeInto(textarea, 'x :wave');
    expect(host.querySelector('.laranail-emoji-picker-suggest')).toBeNull(); // the same code stays dismissed
    typeInto(textarea, 'x :wave :fus');
    expect(host.querySelector('.laranail-emoji-picker-suggest')).not.toBeNull();
    expect(caretRect(textarea)).toHaveProperty('height');
  });

  it('finds an emoji by its emoticon, and by English keywords where the payload carries them', () => {
    const items = [
      { emoji: '🙂', hexcode: '1F642', name: 'visage légèrement souriant', shortcode: 'slight_smile', keywords: ['visage'], version: '1.0', skins: {}, emoticons: [':)', ':-)'], keywords_en: ['happy', 'smile'] },
      { emoji: '😀', hexcode: '1F600', name: 'visage rieur', shortcode: 'grinning', keywords: ['visage'], version: '1.0', skins: {} },
    ];

    expect(search(items, ':)').map((i) => i.hexcode)).toEqual(['1F642']);
    expect(search(items, 'happy').map((i) => i.hexcode)).toEqual(['1F642']);
    expect(search(items, ':grinning:').map((i) => i.hexcode)).toEqual(['1F600']);
  });
});

describe('custom emoji in Frequently used', () => {
  it('remembers a custom pick beside Unicode ones, in the order they were picked', async () => {
    const store = memoryStore();
    const { picker, host, cells } = await mount({ store });

    host.querySelector('[data-laranail-emoji-custom="partyparrot"]').click();
    cells()[0].click();
    picker.categories([]); // redraw now

    const recent = host.querySelector('section[data-laranail-emoji-section="recent"]');
    const labels = [...recent.querySelectorAll('[role="gridcell"]')].map((c) => c.getAttribute('aria-label'));

    expect(labels).toEqual(['grinning face', 'Party parrot']);
    expect(recent.querySelector('[data-laranail-emoji-custom="partyparrot"] img')).not.toBeNull();
    expect(readRecent(store.get('recent')).map((r) => r.base)).toEqual(['1F600', 'custom:partyparrot']);
  });

  it('drops a custom recent the payload no longer has, or when custom emoji are off', () => {
    const recent = [{ base: 'custom:gone', hexcode: 'custom:gone', count: 1, at: 2 }, { base: 'custom:partyparrot', hexcode: 'custom:partyparrot', count: 1, at: 1 }];

    expect(buildSections(payload(), { recent })[0].items.map((i) => i.name)).toEqual(['partyparrot']);
    expect(buildSections(payload(), { recent, custom: false })[0].slug).not.toBe('recent');
  });
});

describe('the build', () => {
  it("gives the shortcut keys their own colours, and the menus a raised surface their caret shares", () => {
    const css = readFileSync(resolve(root, 'public/assets/css/picker.css'), 'utf8');
    const kbd = css.match(/\.laranail-emoji-picker-shortcuts kbd\{([^}]*)\}/);

    // A host's kbd background under the picker's own text colour made the keys unreadable.
    expect(kbd).not.toBeNull();
    expect(kbd[1]).toMatch(/background:/);
    expect(kbd[1]).toMatch(/color:var\(--_lep-fg\)/);
    // The in-panel menus fill their caret with their raised surface, not the panel's colour they sit on.
    expect(css).toMatch(/--_lep-arrow-fill:var\(--_lep-raised\)/);
    expect(css).toMatch(/border-block-(?:end|start)-color:var\(--_lep-arrow-fill,var\(--_lep-bg\)\)/);
  });

  it("keeps a host page's own section styles out of the grid", () => {
    // The sections are <section> elements; a host's `section { padding: … }` pushed the last column out of view.
    const css = readFileSync(resolve(root, 'public/assets/css/picker.css'), 'utf8');
    const rule = css.match(/\.laranail-emoji-picker-section\{([^}]*)\}/);

    expect(rule).not.toBeNull();
    for (const reset of ['margin:0', 'padding:0', 'border:0']) {
      expect(rule[1]).toContain(reset);
    }
  });

  it('reads its public theme tokens without declaring them, so a value set above the picker wins', () => {
    const css = readFileSync(resolve(root, 'public/assets/css/picker.css'), 'utf8');
    const declared = [...css.matchAll(/(?:^|[;{])\s*(--laranail-emoji-picker-[a-z-]+)\s*:/g)].map((m) => m[1]);
    const read = new Set([...css.matchAll(/var\((--laranail-emoji-picker-[a-z-]+)/g)].map((m) => m[1]));

    expect(read.size).toBeGreaterThanOrEqual(12);
    expect(declared).toEqual([]);
    // Page themes (Tailwind, theme switchers, Bootstrap 5.3) under :where(), so the picker's own theme
    // outranks them; light after dark, so an explicit light wins a tie.
    const dark = css.indexOf(':where(.dark,[data-theme=dark],[data-bs-theme=dark]) .laranail-emoji-picker');
    const light = css.indexOf(':where(.light,[data-theme=light],[data-bs-theme=light]) .laranail-emoji-picker');
    const own = css.indexOf('.laranail-emoji-picker[data-theme=dark]');
    expect(dark).toBeGreaterThan(-1);
    expect(light).toBeGreaterThan(dark);
    expect(own).toBeGreaterThan(light);
    expect(css).toMatch(/color-scheme:\s*dark/);
    // The phone bottom sheet is switched by the script (data-sheet), which only a popover gets.
    expect(css).toMatch(/\.laranail-emoji-picker\[data-sheet\] \.laranail-emoji-picker-panel\{/);
    expect(css).not.toMatch(/max-width:\s*480px/);
  });

  it('keeps every export of the module, and declares each one', async () => {
    const built = readFileSync(resolve(root, 'public/assets/js/picker.js'), 'utf8');
    const declared = [...readFileSync(resolve(root, 'resources/assets/types/picker.d.ts'), 'utf8').matchAll(/^export declare (?:class|function|const) (\w+)/gm)].map((m) => m[1]).sort();
    const exported = Object.keys(module).sort();

    expect(exported).toEqual(declared);

    for (const name of exported) {
      expect(built).toMatch(new RegExp(`export\\s*\\{[^}]*\\b${name}\\b`));
    }
  });
});
