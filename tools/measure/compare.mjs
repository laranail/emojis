// Worker/driver: renders each raw SVG and its sanitised twin at 128 px and reports the largest per-channel
// difference, so a sanitiser change that alters how any emoji looks is caught.
//   node tools/measure/compare.mjs <raw-dir> <sanitised-dir>
import { readFileSync, readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const self = fileURLToPath(import.meta.url);

if (process.argv[2] === '--worker') {
  const { Resvg } = await import('@resvg/resvg-js');
  const [raw, clean] = [process.argv[3], process.argv[4]];
  for (const f of readFileSync(0, 'utf8').split('\n').filter(Boolean)) {
    const px = (p) => new Resvg(readFileSync(p, 'utf8'), { fitTo: { mode: 'width', value: 128 } }).render().pixels;
    const a = px(`${raw}/${f}`), b = px(`${clean}/${f}`);
    let max = a.length === b.length ? 0 : 255;
    for (let i = 0; i < a.length && max < 255; i++) max = Math.max(max, Math.abs(a[i] - b[i]));
    process.stdout.write(`${f}\t${max}\n`);
  }
  process.exit(0);
}

const [raw, clean] = process.argv.slice(2);
const files = readdirSync(clean).filter((f) => f.endsWith('.svg'));
const run = (batch) => spawnSync(process.execPath, [self, '--worker', raw, clean], { input: batch.join('\n'), maxBuffer: 1 << 26 });
const parse = (r) => r.stdout.toString().split('\n').filter(Boolean).map((l) => l.split('\t'));
const results = [];
const unrenderable = [];
// resvg can abort the process on some files (a Rust panic JavaScript cannot catch): a batch that dies is
// retried one file per process, and a file that still kills its process is reported, never skipped silently.
for (let i = 0; i < files.length; i += 200) {
  const batch = files.slice(i, i + 200);
  const r = run(batch);
  if (r.status === 0) { results.push(...parse(r)); continue; }
  for (const f of batch) {
    const one = run([f]);
    one.status === 0 ? results.push(...parse(one)) : unrenderable.push(f);
  }
}
const diff = results.filter(([, m]) => Number(m) > 2);
console.log(`${clean}: compared ${results.length}/${files.length}, unrenderable ${unrenderable.length}${unrenderable.length ? ' (' + unrenderable.slice(0, 8).join(' ') + ')' : ''}, differing (>2/255) ${diff.length}${diff.length ? ' e.g. ' + diff.slice(0, 5).map((d) => d.join('=')).join(' ') : ''}`);
if (results.length + unrenderable.length !== files.length || diff.length > 0) process.exitCode = 1;
