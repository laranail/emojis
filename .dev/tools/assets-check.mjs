// Fails when the committed public/assets does not match a fresh build of resources/assets — the same contract
// as `composer sync-check` for the dataset. Builds into a temporary directory and compares file by file.

import { mkdtempSync, readdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative, resolve } from 'node:path';
import { build } from 'vite';

const root = resolve(import.meta.dirname, '../..');
const committed = join(root, 'public/assets');
const fresh = mkdtempSync(join(tmpdir(), 'laranail-emojis-assets-'));

const files = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);

    return statSync(path).isDirectory() ? files(path) : [path];
  });

try {
  await build({ root, configFile: join(root, 'vite.config.mjs'), build: { outDir: fresh }, logLevel: 'error' });

  const want = files(fresh).map((p) => relative(fresh, p)).sort();
  const have = files(committed).map((p) => relative(committed, p)).sort();
  const problems = [];

  if (want.length === 0) {
    problems.push('the build produced no files');
  }

  for (const file of new Set([...want, ...have])) {
    if (!want.includes(file)) {
      problems.push(`${file}: committed but no longer built`);
    } else if (!have.includes(file)) {
      problems.push(`${file}: built but not committed`);
    } else if (!readFileSync(join(fresh, file)).equals(readFileSync(join(committed, file)))) {
      problems.push(`${file}: differs from a fresh build`);
    }
  }

  if (problems.length > 0) {
    console.error(`public/assets is stale — run \`npm run build\` and commit:\n  ${problems.join('\n  ')}`);
    process.exitCode = 1;
  } else {
    console.log(`public/assets matches a fresh build (${want.length} files).`);
  }
} finally {
  rmSync(fresh, { recursive: true, force: true });
}
