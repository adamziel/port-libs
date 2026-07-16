# Pandoc Shared ZIP Package Core Current Base 20260609T070257Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T070257Z`
- Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`
- Behavior cluster: ZIP64 end-of-central-directory locator target diagnostics before native package import.

## Implementation

`ZipPackage` now exposes ZIP64 EOCD locator target evidence in both `endOfCentralDirectoryPreflight()` and `zip64EndOfCentralDirectoryAccountingPreflight()`:

- `recordOffsetAvailable` / `zip64EndOfCentralDirectoryRecordOffsetAvailable`
- `recordSignature` / `zip64EndOfCentralDirectoryRecordSignature`
- `recordSignatureHex` / `zip64EndOfCentralDirectoryRecordSignatureHex`

Malformed locators that point at another available ZIP record now report `zip64-end-of-central-directory-locator-target-not-record`; locators that point beyond available archive bytes report `zip64-end-of-central-directory-locator-target-unavailable`. Raw strict import preflight carries these issues into diagnostics before package instantiation.

## Evidence

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2706 assertions, 0 failures`
- Red-first after adding locator-target expectations: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - failed as expected on missing `recordOffsetAvailable` and missing `zip64-end-of-central-directory-locator-target-not-record`
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2736 assertions, 0 failures`
- Updated WordPress ZIP package example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - `zip package writer preflight self-test passed`

Expected lane movement from this worktree:

- `phpPass`: `2467 -> 2468`
- mapped ZIP package core cases: `22 -> 23`
- focused ZIP package assertions: `2706 -> 2736` for `ZipPackageTest.php`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage` EOCD/ZIP64 parser, raw strict import preflight, and known ZIP record signature inventory. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat earlier ZIP64 EOCD locator/accounting support, package/entry comment policy, extra-field validation, central-directory offset repair, duplicate local-header offset diagnostics, or ZIP64 extended-information extra-field policy. It adds only locator target signature classification for malformed ZIP64 EOCD locator offsets before import.

## Follow-Up

Remaining ZIP package follow-up should stay non-overlapping: ZIP64 package-level instantiation strategy, remaining data-descriptor edge diagnostics, or central-directory repair handoff coverage.
