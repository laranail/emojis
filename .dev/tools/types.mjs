// Generates the picker's TypeScript declarations from its source, so they cannot drift from it.
//
//   node .dev/tools/types.mjs           emit dist/types (for npm) and write resources/assets/types/picker.d.ts,
//                                       which the Vite build ships beside public/assets/js/picker.js
//   node .dev/tools/types.mjs --check   exit 1 when the committed picker.d.ts differs from a fresh emit
//
// picker.ts imports nothing, so its declaration file stands alone and can ship as a single file.

import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const check = process.argv.includes('--check');
const committed = join(root, 'resources/assets/types/picker.d.ts');
const outDir = check ? mkdtempSync(join(tmpdir(), 'laranail-emojis-types-')) : join(root, 'dist/types');
const header = '// GENERATED from resources/assets/scripts/picker.ts by .dev/tools/types.mjs — do not edit.\n';

try {
  execFileSync(join(root, 'node_modules/.bin/tsc'), ['-p', join(root, 'tsconfig.types.json'), '--outDir', outDir], { stdio: 'inherit' });

  const generated = header + readFileSync(join(outDir, 'scripts/picker.d.ts'), 'utf8');

  if (check) {
    let current = '';

    try {
      current = readFileSync(committed, 'utf8');
    } catch {
      // Missing reads as stale.
    }

    if (current !== generated) {
      console.error('resources/assets/types/picker.d.ts is stale — run `npm run types` and commit.');
      process.exitCode = 1;
    } else {
      console.log('picker.d.ts matches its source.');
    }
  } else {
    writeFileSync(committed, generated);
    console.log(`Wrote resources/assets/types/picker.d.ts and ${outDir.replace(root + '/', '')}.`);
  }
} finally {
  if (check) {
    rmSync(outDir, { recursive: true, force: true });
  }
}
