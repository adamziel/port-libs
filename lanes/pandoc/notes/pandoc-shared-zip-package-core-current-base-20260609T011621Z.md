# Pandoc ZIP Package Core Current-Base Handoff

Slice: `pandoc-shared-zip-package-core-current-base-20260609T011621Z`

Accepted base: `403bbfa850b87a30b18d0488738d4e785be58580`

## Behavior

`ZipPackage` data-descriptor preflights now expose descriptor boundary provenance for descriptor-backed entries:

- `nextOffset`
- `descriptorSpan`
- `descriptorEnd`
- `surplusDescriptorBytes`
- `truncatedDescriptorBytes`

The strict raw preflight and package import path remain fail-closed for descriptor slack before the next local header. The new metadata makes the boundary mismatch reviewer-visible before DOCX, EPUB, ODT, or WordPress package media import.

## Non-Overlap

This slice covers data-descriptor boundary accounting only. It does not repeat the accepted invalid DOS timestamp writer slice, Unicode-name collision checks, central-directory signature accounting, trailing-deflate payload integrity, ZIP64 accounting, package comments, or local-header offset slices.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2072 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2083 assertions, 1 failures`; the new descriptor slack test was missing `descriptorSpan`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2095 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.

Final syntax and whitespace verification is recorded in `lane-status.json` for the exported handoff.

## Status Delta

- `phpPass`: `2029` -> `2030`
- Focused ZIP package assertion delta: `2072` -> `2095`
- New mapped focused case: descriptor-backed entry slack before the next local header is rejected while exposing descriptor span and surplus-byte metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP `ZipPackage` data-descriptor parser, strict raw import preflight, and WordPress ZIP package preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`/`unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Next Task

A next non-overlapping ZIP package slice could cover package-reader media handoff summaries for selected entry sets, or ZIP64 EOCD locator follow-up not already covered by existing accounting tests.
