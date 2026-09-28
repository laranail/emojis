# Release

How `laranail/emojis` is versioned and released, and how the dataset moves to a new Unicode version.

## Versioning

Pre-1.0, the package follows the laranail family model: one moving `v0.1.0` tag, with a
`dev-main → 0.1.x-dev` branch alias. Require it as `^0.1`.

Composer caches a dist archive per package reference, and a moved tag keeps its name, so after a tag move
`composer update` can report success and install the archive it cached the first time. Clear the cache
when you need the new code:

```bash
composer clear-cache && composer update laranail/emojis
```

## Releasing

1. Land the change on `main` through a pull request; CI must be green.
2. Move `## [Unreleased]` in `CHANGELOG.md` into the version section.
3. Move the tag to the merged commit. The tag-driven `release.yml` builds the release notes from the
   version's CHANGELOG section (and fails if there is none), attaches a CycloneDX SBOM, and updates the
   existing GitHub release in place when the tag moves.

## Moving to a new Unicode or CLDR version

Unicode publishes a new Emoji version each September; CLDR follows. The weekly `Refresh upstream data`
workflow does the steps below and opens a pull request; review its report (and the dataset diff) like code.
To do it by hand, `php .dev/tools/refresh.php` runs them all, or:

1. Update the URLs and versions in `database/sources/upstream.lock.json`.
2. `php .dev/tools/build-dataset.php --fetch`, then `php .dev/tools/build-dataset.php --lock`.
3. `php .dev/tools/build-dataset.php && php .dev/tools/generate-enums.php`.
4. Review `build/dataset-report.txt` (shortcode collisions, dropped codes) and the diff of `database/generated/`.
5. Run `composer lint && composer test`. The whole-dataset suite fails if any emoji stops round-tripping.

Existing `EmojiId` cases are never renamed. Image-set versions are pinned alongside their coverage data, so
bump an image set in the lock file rather than pointing at `@latest`.

---

[← Docs index](../README.md#documentation)
