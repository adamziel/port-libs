# Pandoc ZIP Package Control-Byte Name Slice

Date: 2026-06-07 UTC

Slice: `pandoc-shared-zip-package-core-current-base-20260607T024809Z`

Accepted base: `c0189ee9c433a90073c4136e67c4f8566a365749`

## Behavior

`ZipPackage` now rejects package part names containing C0 control bytes, DEL, or Unicode control characters before exposing entries or writing generated package parts. The check applies to raw central-directory names, generated `fromParts()` names, and decoded Info-ZIP Unicode path metadata while preserving ordinary spaces and dashes in safe media names.

This closes a bounded ZIP/OPC package primitive gap for DOCX/EPUB/ODT media handoff without invoking Pandoc, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, Cabal, Haskell runners, external archive tools, online services, live provider tests, or live-service provider tests.

## Source Truth

The behavior follows the existing native package path policy already enforced by `ZipPackage::assertSafePartName()`: unsafe traversal, absolute paths, drive-letter paths, backslashes, empty path segments, and NUL-like controls must fail before the Office/EPUB/ODT readers expose package entries to higher-level converters.

## Evidence

Baseline from the previously accepted ZIP package slice:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1012 assertions, 0 failures`

Red-first focused run after adding the control-byte case:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1013 assertions, 1 failures`

Failure: expected `RuntimeException` was not thrown for a control-byte ZIP entry name.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1019 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `1440 -> 1441`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1857 -> 1858`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 168`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage` validation, in-memory ZIP fixtures, the WordPress ZIP package preflight example, and the focused lane PHP harness. Full Pandoc/Haskell runner parity remains intentionally out of scope until external runner authorization exists.

## Non-Overlap

This slice does not touch split archive disk markers, ZIP64 EOCD/locator handling, central-directory signatures, invalid DOS timestamps, Unicode normalization collisions, symlink/special-file metadata, deflate trailing-byte integrity, data descriptors, NTFS/extended timestamps, or OPC relationship/content-type semantics.

## Follow-Up

Keep ZIP follow-up bounded to non-overlapping package primitives such as relationship/content-type handoff or remaining local/central metadata diagnostics. Do not use Pandoc, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, Cabal, Haskell runners, external archive tools, or online services for progress in this lane.
