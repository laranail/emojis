import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payload } from './fixture.mjs';

globalThis.__laranailEmojiNoAutoInit = true;

const module = await import('../../resources/assets/scripts/picker.ts');
const { Picker, StaticSource, ApiSource, memoryStore, localStorageStore, fold, charOf, withTone, search, sortItems, recordRecent, orderRecent, parseOptions, autoInit, byVersion, detectMaxVersion, buildSections, searchSections, capPayload, insertText, indexPayload } = module;

const root = resolve(import.meta.dirname, '../..');
const flush = () => new Promise((r) => setTimeout(r, 0));

const mount = async (options = {}) => {
  document.body.replaceChildren();
  const host = document.createElement('div');
  const textarea = document.createElement('textarea');
  document.body.append(host, textarea);
  const picker = await Picker.create(host, { inline: true, store: memoryStore(), ...options }).source(new StaticSource(payload())).target(textarea).mount();

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

  it('detects the newest version a canvas draws, treating tofu and split sequences as missing', () => {
    // A fake canvas whose font knows emoji up to 14.0: newer singles draw as the tofu box, sequences split.
    const known = new Set(['\u{1FAE0}', '\u{1F972}', '\u{1F600}']);
    const context = {
      font: '', textBaseline: '', last: '',
      clearRect() {}, fillText(text) { this.last = text; },
      getImageData() { return { data: [known.has(this.last) ? this.last : 'tofu'] }; },
      measureText: (text) => ({ width: [...text].length > 2 ? 60 : 24 }),
    };
    const doc = { createElement: () => ({ getContext: () => context }) };

    expect(detectMaxVersion(doc)).toBe('14.0');
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
  afterEach(() => vi.restoreAllMocks());

  it('loads from the API with the locale, and fails loudly on an error status', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: payload() }), { status: 200 }));

    await expect(new ApiSource('/laranail/emojis/api/v1/').load('fr')).resolves.toMatchObject({ locale: 'en' });
    expect(fetch.mock.calls[0][0]).toBe('/laranail/emojis/api/v1/picker?locale=fr');

    fetch.mockResolvedValue(new Response('{}', { status: 500 }));
    await expect(new ApiSource('/x/picker').load()).rejects.toThrow('HTTP 500');
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
    expect(host.querySelector('[role="status"]').textContent).toBe('1 results');

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
    key(cells()[1], 'ArrowDown');
    expect(document.activeElement).toBe(cells()[3]);
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

    expect(await Picker.create(host).mount()).not.toBe(picker);
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

describe('the build', () => {
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
