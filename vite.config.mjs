// Builds every entry under resources/assets into public/assets, one directory per kind:
//
//   resources/assets/styles/<name>.scss   → public/assets/css/<name>.css   (files starting "_" are partials)
//   resources/assets/scripts/<name>.{js,ts} → public/assets/js/<name>.js
//   resources/assets/types/<name>.d.ts      → public/assets/js/<name>.d.ts  (copied as-is)
//   anything they import (fonts, images)  → public/assets/<ext>/<name>.<ext>
//
// Names are not hashed: public/assets is committed, read by Emojis::stylesheet() for inlining, and published
// to public/vendor/laranail/emojis by `php artisan vendor:publish --tag=laranail::emojis-assets`, so a URL
// must survive a rebuild. `npm run assets-check` fails when the committed build is stale.

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { resolve, parse } from 'node:path';
import { defineConfig } from 'vite';

const root = import.meta.dirname;

const entries = (dir, extensions) => {
  const path = resolve(root, dir);

  return existsSync(path)
    ? readdirSync(path)
        .filter((file) => !file.startsWith('_') && extensions.includes(parse(file).ext))
        .map((file) => resolve(path, file))
    : [];
};

// Keyed by output directory, so a stylesheet and a script may share a name (picker.scss, picker.js) without
// Rollup renaming one of them picker2.
const input = Object.fromEntries([
  ...entries('resources/assets/styles', ['.scss', '.css']).map((path) => [`css/${parse(path).name}`, path]),
  ...entries('resources/assets/scripts', ['.js', '.ts']).map((path) => [`js/${parse(path).name}`, path]),
]);

if (Object.keys(input).length === 0) {
  throw new Error('No entries under resources/assets/{styles,scripts}.');
}

// Hand-written declarations for the scripts (resources/assets/types/<name>.d.ts) ship beside the build as
// js/<name>.d.ts, so TypeScript users get types and assets-check holds them to their source too.
const declarations = () => ({
  name: 'laranail-emojis-declarations',
  generateBundle() {
    for (const file of entries('resources/assets/types', ['.ts']).filter((path) => path.endsWith('.d.ts'))) {
      this.emitFile({ type: 'asset', fileName: `js/${parse(file).base}`, source: readFileSync(file, 'utf8') });
    }
  },
});

export default defineConfig({
  publicDir: false,
  plugins: [declarations()],
  logLevel: 'warn',
  build: {
    outDir: 'public/assets',
    emptyOutDir: true,
    copyPublicDir: false,
    manifest: false,
    cssCodeSplit: true,
    target: 'es2022',
    rollupOptions: {
      input,
      // Keep every export of an entry: picker.js is imported by applications (Picker, ApiSource, …), and the
      // default would let Rollup drop exports nothing inside the bundle uses.
      preserveEntrySignatures: 'strict',
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: ({ names }) => (names[0]?.endsWith('.css') ? '[name][extname]' : '[ext]/[name][extname]'),
      },
    },
  },
});
