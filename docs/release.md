# Release

How `laranail/emojis` is versioned and released, and how the dataset moves to a new Unicode version.

## Versioning

The package carries **one tag, `v0.1.0`, and moves it** on each change, which is the laranail
family's pre-1.0 model. Consumers require `^0.1`:

```bash
composer require laranail/emojis:^0.1
```

Three places name the live line and must agree: the newest `## [X.Y.Z]` section in `CHANGELOG.md`,
the `dev-main` branch alias in `composer.json` (`0.1.x-dev`), and the install commands in
`README.md` and `docs/installation.md`, the README's `npm install @laranail/emojis-picker@^0.1`
among them. `tests/Unit/DocumentationTest.php` fails when they drift.

The 13 tags cut between 2026-09-26 and 2026-10-20 (`v0.1.0` through `v0.8.2`) were withdrawn when
the package returned to this model. Their entries are kept in `CHANGELOG.md` under
`## [Internal history] (not published)`, relabelled rather than deleted.

### What the moving tag costs, and what to tell consumers

**A moved tag does not reach a consumer who has already fetched it.** Composer caches the dist
archive per package *reference*, and the reference is the tag name, which never changes — so
`composer update` reports success, resolves `v0.1.0`, and unpacks the archive it cached the first
time. Verified across the family on 2026-08-30: a clean install resolved eight laranail packages at
`v0.1.0` and contained none of a day's pushed work, and `composer clear-cache` then produced the
current code from the same tag. So:

- Tell consumers to run `composer clear-cache` after a tag move. Checking the remote tag is not
  enough — verify the files under `vendor/`, in a cache you have cleared.
- Reading the tag's commit on GitHub proves nothing about what a consumer receives.

**The org-wide release-currency check is built on the opposite model.** `laranail/.github`'s
reusable `hygiene.yml` runs `laranail/package-tools`' `verify-tag-currency.sh`, whose header reads
"Release tags are immutable … nothing it prints suggests moving a tag". It still passes here — it
requires every tag to be an ancestor of `main` and the branch-alias line to have a tag, both of
which a freshly moved `v0.1.0` satisfies — but it will never tell you a move is due. Treat a
"release due" warning on this repository as "move the tag".

**The JavaScript package cannot follow the moving tag, and does not try to.** GitHub Packages holds
`@laranail/emojis-picker` up to 0.8.2 and a published version there is immutable, so publishing `0.1.0`
from a moving tag would freeze the package at whatever the first push contained — every later move would
find the version already present and skip it. It also cannot take the `latest` tag while 0.8.2 is
published: a tag push failed with *"Cannot implicitly apply the \"latest\" tag because previously
published version 0.8.2 is higher than the new version 0.1.0"*. So `npm-publish.yml` is
`workflow_dispatch` only, the README's npm constraint is `^0.8` rather than `^0.1`, and a JavaScript
release is its own decision with its own increasing version:

```bash
gh workflow run npm-publish.yml --repo laranail/emojis -f tag=<tag> -f registry=github
```

Check what a registry actually holds before assuming a version is free. `repo.packagist.org` and
`registry.npmjs.org` both answer 404 for this package, and generalising from that missed GitHub Packages
entirely; `/orgs/{org}/packages` needs `read:packages`, which the CLI app token does not carry, so the
answer comes from `npm view <pkg>@<version> --registry https://npm.pkg.github.com` with a token.

**This repository has no tag ruleset**, which is what makes a move possible at all; the family
standard otherwise asks for one blocking deletion and update of `v*`. The two cannot both hold.

## Releasing

1. Land the change on `main` through a pull request; CI must be green.
2. Rewrite the `## [0.1.0]` section in `CHANGELOG.md` so it describes the code the tag will point
   at, and move anything under `## [Unreleased]` into it. `release.yml` refuses a tag while
   `[Unreleased]` still has entries, and builds the release notes from the version's own section.
3. **Land the branch first, then move the tag.** The branch ruleset requires a pull request while
   tags are unprotected, so `git push --force origin v0.1.0` succeeds even when a direct push to
   `main` is refused — which is how a tag ends up on a commit that is on no branch. Composer's VCS
   driver reads tags, not reachability, and would offer that orphan as newest.
4. Move the tag onto the merge commit:

   ```bash
   git -C <checkout> fetch origin main:main
   git -C <checkout> tag -f v0.1.0 main
   git -C <checkout> push --force origin v0.1.0
   ```

5. Verify on the remote, not locally — and dereference, because an annotated tag's ref SHA is the
   tag object, never a commit:

   ```bash
   git ls-remote origin 'refs/tags/v0.1.0^{}'   # falls back to refs/tags/v0.1.0 if lightweight
   ```

6. The tag push re-runs `release.yml` against a release that already exists, so it must stay
   idempotent: view-then-edit, never `gh release create`, and re-upload assets with `--clobber`.

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
