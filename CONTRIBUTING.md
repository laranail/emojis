# Contributing

Thanks for helping improve `laranail/emojis`.

## Getting set up

```bash
composer install
composer test
composer lint
```

Requires PHP `^8.4.1 || ^8.5` with `ext-mbstring`. `ext-intl` is optional.

## What must pass

- **Style** — `composer pint-fix`, then `composer pint` (the family config from `laranail/package-tools`;
  there is no local `pint.json`). The generated shards are linted too and are emitted Pint-clean.
- **Static analysis** — `composer phpstan` runs two configs: level 8 with larastan for the Laravel shell,
  and **level 10 with strict rules and no baseline for `src/Core`**.
- **Architecture** — `composer deptrac` proves `src/Core` references nothing outside itself and
  `psr/log`. It runs through `tools/deptrac-guard.php`, because deptrac exits 0 when it cannot parse a file.
- **Core isolation** — `composer core-isolation` runs the Core API under an autoloader that refuses every
  class outside Core and `psr/log`, which is the runtime proof deptrac cannot give.
- **Rector** — `composer rector` (dry run), pinned to the `php84` set to match the floor.
- **Generated data** — `composer sync-check`. See below.
- **Tests** — `composer test`. Unit and Datasets suites never boot Laravel; Feature does.

## Generated files

Two generators, chained:

1. `tools/build-dataset.php` builds every shard in `database/generated/` (and `docs/licences.md`) from the
   upstream sources pinned in `database/sources/upstream.lock.json` (URL + sha256), the hand-curated JSON in
   `database/sources/curated/` and the image margins in `database/sources/measured/`.
2. `tools/generate-enums.php` builds `src/Core/Enums/{Group,Subgroup,EmojiVersion,EmojiId}.php` from the
   committed catalogue.

**Never hand-edit a generated file.** Change an overlay or a generator and re-run:

```bash
php tools/build-dataset.php --fetch    # download any missing source, verify, and write
php tools/build-dataset.php --check    # CI gate
php tools/generate-enums.php           # write the enums
composer sync-check                    # both checks
```

Sources are cached in `build/cache/sources` (gitignored). Without them, `--check` skips outside CI; in CI
it fetches and never skips.

### Bumping a source

1. Change the URL and `version` in `database/sources/upstream.lock.json`.
2. `php tools/build-dataset.php --fetch`, then `php tools/build-dataset.php --lock` to record the new sha256.
3. `php tools/build-dataset.php && php tools/generate-enums.php`.
4. Read `build/dataset-report.txt` for new shortcode collisions and drops, and review the diff of
   `database/generated/` like code.

`EmojiId` case names are public API: the generator keeps every existing case and only adds new ones.

## Images and the SVG sanitiser

`tools/measure/` holds the maintainer tools behind image fit and the local image store: `measure-bounds.mjs`
(margins), `hash-images.php` (the SHA-256 the installer verifies) and `compare.mjs`. After changing
`src/Core/Image/SvgSanitizer.php`, sanitise every cached image and run `compare.mjs` on each set; it rasterises
each file before and after and must report 0 differing images. `tools/refresh.php` runs the first two when an
image set moves.

## Front-end assets

`resources/assets/styles/*.scss` is the source; `public/assets/` is the committed Vite build, so Composer
installs need no Node. After changing the source run `npm install && npm run build` and commit both.
`npm run assets-check` (the `Assets` workflow) fails when they disagree. No `package-lock.json` is committed.

## The scanner

`src/Core/Text/Scanner.php` does not use PCRE Unicode emoji properties (`\p{Extended_Pictographic}` and
friends), PCRE JIT, or ICU grapheme rules — PHP builds still ship PCRE2 10.36 and ICU 57, which have none
of them right. The generated character classes and the length-bucketed hash lookup are the portable
replacement. `tests/Arch/BoundaryTest.php` fails if a property sneaks back in.

## Pull requests

Branch from `main`, keep the change focused, add tests, and add a line under `## [Unreleased]` in
`CHANGELOG.md` for anything user-facing. Commits are imperative, subject at most 72 characters.

## Conduct

See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Security issues go to `security@simtabi.com`, not the tracker —
see [SECURITY.md](SECURITY.md).
