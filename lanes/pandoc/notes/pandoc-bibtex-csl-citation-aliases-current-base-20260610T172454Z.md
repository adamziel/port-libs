# BibTeX/CSL citation alias handoff slice

## Scope

- Map BibLaTeX `ids` aliases in compact `BibtexCslProcessor` handoff.
- Resolve alias citation keys to the canonical CSL item while preserving alias provenance.
- Suppress duplicate canonical bibliography entries when a cluster cites both alias and canonical keys.
- Keep missing alias diagnostics visible for reviewer handoff.

## Implementation

- `BibtexCslProcessor::toCslItem()` now normalizes `ids` into `citationAliases` and a summary string.
- `cslItems()` exposes non-conflicting alias lookup keys as CSL item copies with `citationAlias` metadata.
- `citationHandoff()` deduplicates emitted bibliography items by canonical CSL `id`.

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - Failed before implementation because only `canonical-source` was exposed as a CSL lookup key.
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 test file, 101 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60883 assertions, 0 failures.

## Accounting

- `phpPass` increments from 2993 to 2994.
- `phpFail` remains 0.
- The mapped denominator increments from 3150 to 3151.
- No Pandoc, BibTeX, Biber, citeproc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.
