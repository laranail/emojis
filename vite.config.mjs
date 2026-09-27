// Builds every entry under resources/assets into public/assets, one directory per kind:
//
//   resources/assets/styles/<name>.scss   → public/assets/css/<name>.css   (files starting "_" are partials)
//   resources/assets/scripts/<name>.{js,ts} → public/assets/js/<name>.js
//   anything they import (fonts, images)  → public/assets/<ext>/<name>.<ext>
//
// Names are not hashed: public/assets is committed, read by Emojis::stylesheet() for inlining, and published
// to public/vendor/laranail/emojis by `php artisan vendor:publish --tag=laranail::emojis-assets`, so a URL
// must survive a rebuild. `npm run assets-check` fails when the committed build is stale.

import { readdirSync, existsSync } from 'node:fs';
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

const input = [
  ...entries('resources/assets/styles', ['.scss', '.css']),
  ...entries('resources/assets/scripts', ['.js', '.ts']),
];

if (input.length === 0) {
  throw new Error('No entries under resources/assets/{styles,scripts}.');
}

export default defineConfig({
  publicDir: false,
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
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: ({ names }) => (names[0]?.endsWith('.css') ? 'css/[name][extname]' : '[ext]/[name][extname]'),
      },
    },
  },
});
