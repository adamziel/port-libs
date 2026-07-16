# Pandoc ZIP Package Current-Base: Invalid DOS Modification Timestamps

Slice: `pandoc-shared-zip-package-core-current-base-20260606T184339Z`

Accepted base: `d58a45056308ade34ea13cdabb81f621d495fada`

## Behavior

- Added native `ZipPackage::modificationTimePreflight()` and `ZipPackage::assertValidModificationTimes()` for package-level timestamp provenance before DOCX/EPUB/ODT media handoff.
- Exposed per-entry DOS timestamp presence, decoded DOS timestamp, extended timestamp, NTFS timestamp, selected import timestamp source, and invalid DOS timestamp issues.
- Folded invalid DOS date/time metadata into strict package preflight as `invalid-modification-times`.
- Kept payload reads available before strict handoff rejection so reviewers can inspect exact package bytes without external archive tools.

## Non-Overlap

This slice does not repeat the accepted central-directory signature policy, duplicate Info-ZIP Unicode path/comment metadata, DOS hidden/system/volume attributes, Unix file type/name shape checks, local header span checks, symlink rejection, ZIP64 rejection, AES/encryption rejection, or trailing-deflate payload integrity work.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed with `1 test files, 880 assertions, 1 failures` because `ZipPackage::modificationTimePreflight()` was undefined.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 911 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed with `zip package writer preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ZipPackage.php`, `lanes/pandoc/src/ZipPackageEntry.php`, `lanes/pandoc/tests/ZipPackageTest.php`, and `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1388 -> 1389`.
- `UPSTREAM_TEST_MANIFEST.json` mapped support cases: `1801 -> 1802`.
- ZIP package core support cases: `22 -> 23`.
- ZIP package core assertions: `161 -> 192`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, `ZipPackageEntry`, the focused ZIP package tests, and the WordPress ZIP package preflight example. No Pandoc, Cabal/Haskell runner, zip/unzip, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was run.

## Follow-Up

Continue ZIP/OPC package closure with a non-overlapping package primitive such as central-directory/local-header consistency, package part ordering, or OPC relationship handoff behavior.
