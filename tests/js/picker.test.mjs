import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payload } from './fixture.mjs';

globalThis.__laranailEmojiNoAutoInit = true;

const module = await import('../../resources/assets/scripts/picker.ts');
const { Picker, StaticSource, ApiSource, memoryStore, localStorageStore, fold, charOf, withTone, search, sortItems, recordRecent, orderRecent, parseOptions, autoInit, byVersion, detectMaxVersion, buildSections, searchSections, capPayload, insertText, indexPayload, customCode, mountElement, readRecent, clampTone, clampColumns, searchCustom, rovingIndex } = module;

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

describe('the build', () => {
  it('reads its public theme tokens without declaring them, so a value set above the picker wins', () => {
    const css = readFileSync(resolve(root, 'public/assets/css/picker.css'), 'utf8');
    const declared = [...css.matchAll(/(?:^|[;{])\s*(--laranail-emoji-picker-[a-z-]+)\s*:/g)].map((m) => m[1]);
    const read = new Set([...css.matchAll(/var\((--laranail-emoji-picker-[a-z-]+)/g)].map((m) => m[1]));

    expect(read.size).toBeGreaterThanOrEqual(12);
    expect(declared).toEqual([]);
    expect(css).toMatch(/\.dark \.laranail-emoji-picker/);
    expect(css).toMatch(/\[data-theme=dark\] \.laranail-emoji-picker/);
    // The phone bottom sheet applies to the popover only, never to an inline picker.
    expect(css).toMatch(/max-width:\s*480px\)\{\.laranail-emoji-picker:has\(\.laranail-emoji-picker-trigger\) \.laranail-emoji-picker-panel/);
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
