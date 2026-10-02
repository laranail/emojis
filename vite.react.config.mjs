// Builds the React adapter (resources/assets/react) into dist/react for the npm package
// @laranail/emojis-picker. Not part of the Composer package's committed build: `npm run build:react`.
//
// React stays external (a peer dependency). __LARANAIL_EMOJI_AUTO_INIT__ is false here, so the vanilla
// picker's import-time auto-init, and the DOM Picker it is the only user of, are compiled out.

import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const root = import.meta.dirname;

export default defineConfig({
  publicDir: false,
  logLevel: 'warn',
  define: { __LARANAIL_EMOJI_AUTO_INIT__: 'false' },
  build: {
    outDir: 'dist/react',
    emptyOutDir: true,
    target: 'es2022',
    lib: {
      entry: resolve(root, 'resources/assets/react/index.ts'),
      formats: ['es'],
      fileName: () => 'index.js',
    },
    rollupOptions: {
      external: ['react', 'react/jsx-runtime', 'react-dom'],
    },
  },
});
