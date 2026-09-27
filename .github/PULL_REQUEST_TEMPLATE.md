# Summary

<!-- What changes, and why. The why matters more. -->

## Checklist

- [ ] `composer lint` passes (parallel-lint, Pint, PHPStan ×2, deptrac, Rector, sync-check, core isolation)
- [ ] `composer test` passes
- [ ] If `database/sources/` or `tools/` changed: `composer build-dataset` was run and the regenerated
      `database/generated/` (and `docs/licences.md`) is committed in the same pull request
- [ ] `CHANGELOG.md` has an entry under `## [Unreleased]`, if this is user-facing

## Notes for the reviewer

<!--
Anything touching src/Core/Text/Scanner.php: say which PCRE and ICU versions you ran it on. The scanner is
deliberately independent of PCRE Unicode properties, PCRE JIT and ICU grapheme rules; a "simpler" regex
using \p{Extended_Pictographic} breaks every PHP build still linking PCRE2 < 10.40.
-->
