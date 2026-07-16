# Pandoc ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260608T210133Z`
Base: `0091df3813ad73254e2c1f230ab975292c14a7c0`
Date: 2026-06-08 UTC

## Behavior

Implemented bounded ZIP64 EOCD record-size preflight in native PHP. `ZipPackage` now reports declared ZIP64 EOCD payload size, total record size, computed record end offset, whether the record ends at the locator, and extensible data sector size. It also surfaces fail-closed diagnostics for undersized records, gaps before the locator, and records whose declared size overlaps the ZIP64 locator while preserving the existing strict ZIP64 import rejection.

This does not extract ZIP64 packages. It improves package preflight diagnostics needed by Office/EPUB/ODT container readers.

## Non-Overlap

This slice does not repeat the accepted ZIP package clusters for central-directory signatures, trailing deflate streams, Unicode name collisions, invalid DOS timestamps, data descriptors, local header spans, split archives, ZIP64 extra/local-header sentinels, malformed ZIP64 locators, ZIP64 EOCD field mismatch, empty-package strict import, writable permissions, or EOCD signatures in package comments.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php` - passed.
- `php -l lanes/pandoc/tests/ZipPackageTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - `1 test files, 1850 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` - passed.
- `git diff --check -- lanes/pandoc` - passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses the native `ZipPackage` ZIP64 EOCD parser, raw strict import preflight, focused PHP tests, and the lane-local WordPress ZIP package preflight example. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, external archive tools, online services, live provider tests, and live-service provider tests were not executed.

## Next

Consider a non-overlapping ZIP package follow-up such as ZIP64 extensible-data review policy in container readers, multi-disk ZIP64 fail-closed diagnostics, or additional central/local size reconciliation.
