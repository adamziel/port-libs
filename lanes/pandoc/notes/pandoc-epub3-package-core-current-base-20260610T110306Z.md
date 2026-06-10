# EPUB Compact NCX Spine TOC Binding Diagnostics

Slice: `pandoc-epub3-package-core-current-base-20260610T110306Z`
Date: 2026-06-10 UTC

## Behavior

This slice adds compact EPUB package validation for OPF `spine toc` bindings:

- `validation.ncx` now reports the requested `toc` id, the matching manifest
  item when present, discovered NCX manifest items, selected NCX fallback item,
  and diagnostics.
- Missing `toc` manifest ids are reported as
  `missing-spine-toc-manifest-item`.
- `toc` ids bound to non-NCX manifest items are reported as
  `spine-toc-non-ncx-manifest-item`.
- Fallback NCX navigation remains recoverable through manifest scan when the
  explicit `spine toc` binding is unusable.

The report is exposed through `validationReport()`, `summary().validation`, and
`summary().wordpressImport.packageValidation`.

## Evidence

Focused syntax checks:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`

Focused test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 732 assertions, 0 failures`

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `44 test files, 59588 assertions, 0 failures`

Lane accounting:

- `phpPass`: `2952 -> 2953`
- `phpFail`: remains `0`
- Static mapped denominator: `3124 -> 3125`
- Added counters: `mappedEpubNcxSpineTocBindingCases = 1`,
  `epubNcxSpineTocBindingAssertions = 32`

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`EpubPackage`, existing NCX parsing, existing compact validation reports, and
the lane-local PHP test runner.

No Pandoc, EPUBCheck, `zip`/`unzip`, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat prior EPUB nav heading/item diagnostics, duplicate target
diagnostics, OCF sidecar handling, content feature reconciliation, manifest
fallback chains, encryption, media-overlay, or rich `EpubReader` NCX metadata
work. It is limited to compact OPF `spine toc` binding diagnostics and fallback
NCX selection.
