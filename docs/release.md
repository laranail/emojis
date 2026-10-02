# Release

How `laranail/emojis` is versioned and released, and how the dataset moves to a new Unicode version.

## Versioning

The package cuts a new tag for every release (`v0.1.1`, `v0.1.2`, `v0.2.0`, …) and never moves one. Before
1.0 a caret constraint stops at the minor version: `^0.2` means `>=0.2.0 <0.3.0`. So each new minor version
is a line consumers must opt into, and the install command names the current one:

```bash
composer require laranail/emojis:^0.4
```

Three places name the current line, and they must agree: the newest version in `CHANGELOG.md`, the
`dev-main` branch alias in `composer.json` (`0.4.x-dev`), and the install commands in `README.md` and
`docs/installation.md`. `tests/Unit/DocumentationTest.php` fails when they drift. They did once: after
`v0.2.0` the alias still said `0.1.x-dev` and the docs still said `^0.1`, so anyone following them installed
`v0.1.2`, and the weekly release-currency check compared `v0.1.2` against `main`.

## Releasing

1. Land the changes on `main` through pull requests; CI must be green.
2. Open a `release/vX.Y.Z` pull request with one `Release X.Y.Z` commit: rename `## [Unreleased]` in
   `CHANGELOG.md` to `## [X.Y.Z] - <date>`. For a new minor version, also move the branch alias and the
   install commands to the new line.
3. After it merges, tag the merge commit, `git tag vX.Y.Z && git push origin vX.Y.Z`. The tag-driven
   `release.yml` refuses a tag while `[Unreleased]` still has entries, builds the release notes from the
   version's section (and fails if there is none), and attaches a CycloneDX SBOM.

## Moving to a new Unicode or CLDR version

Unicode publishes a new Emoji version each September; CLDR follows. The weekly `Refresh upstream data`
workflow does the steps below and opens a pull request; review its report (and the dataset diff) like code.
To do it by hand, `php .dev/tools/refresh.php` runs them all, or:

1. Update the URLs and versions in `database/sources/upstream.lock.json`.
2. `php .dev/tools/build-dataset.php --fetch`, then `php .dev/tools/build-dataset.php --lock`.
3. `php .dev/tools/regenerate.php`: every generator in order, the dataset, the enums, and the docs built from
   the dataset (`docs/tools/emoticons.md`, `docs/tools/emoji-list*.md`). `composer sync-check` runs the same
   list with `--check`, so a generated page left stale fails CI like a stale shard.
4. Review `build/dataset-report.txt` (shortcode collisions, dropped codes) and the diff of `database/generated/`.
5. Run `composer lint && composer test`. The whole-dataset suite fails if any emoji stops round-tripping.

Existing `EmojiId` cases are never renamed. Image-set versions are pinned alongside their coverage data, so
bump an image set in the lock file rather than pointing at `@latest`.

---

[← Docs index](../README.md#documentation)
