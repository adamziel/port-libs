# Pandoc Shared ZIP Package Core Current Base

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260611T164202Z`
Accepted base: `16f638244`
Date: 2026-06-11 UTC

## Behavior

This slice promotes `ZipPackage::centralDirectoryVariableFieldsPreflight()`
failures into `ZipPackage::rawStrictImportPreflight()` diagnostics before a
shared ZIP/OPC package is instantiated.

Unsupported central-directory variable-field summaries now add the aggregate
`central-directory-variable-field-issues` diagnostic and retain the underlying
issue codes, so DOCX, EPUB, and ODF package review queues can see these
package-level blockers even when later package construction fails.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 3199 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 65445 assertions, 0 failures`.

## Status Delta

- Added one focused ZIP package PASS case:
  `preflights zip central directory variable field issues before raw strict import`.
- `lane-status.json` `phpPass` moved from `3102` to `3103`.
- `lane-status.json` records 1 mapped ZIP central-directory variable-field
  issue case with 16 focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP ZIP EOCD,
central-directory variable-field, split-marker, and raw strict import helpers.
No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, browser renderer, external validator, online service, live
provider test, or live-service provider test was run.

## Next

A non-overlapping ZIP/OPC follow-up could cover remaining package reader
consumption of raw strict diagnostics, ZIP64 edge provenance, or package media
policy gaps not already covered by archive-extra, descriptor, timestamp,
name-hygiene, encryption, and central-directory inventory slices.
