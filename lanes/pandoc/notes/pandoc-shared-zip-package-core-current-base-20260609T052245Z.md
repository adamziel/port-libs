# Pandoc Shared ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T052245Z`

Accepted base: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Behavior

- Added `ZipPackage::endOfCentralDirectoryOffsetPreflight()` as a non-instantiating EOCD pointer summary for raw ZIP bytes.
- The preflight prefers the existing plausible EOCD candidate first, then falls back to a bounded raw EOCD record scan only when the strict candidate cannot be found. This keeps package comments containing EOCD signatures from becoming false positives while still exposing malformed EOCD central-directory pointers.
- `ZipPackage::rawStrictImportPreflight()` now includes `endOfCentralDirectoryOffset` and emits specific diagnostics such as `central-directory-offset-not-central-header` when an EOCD central-directory offset points at local-file-header bytes.
- The WordPress ZIP package preflight example now includes a malformed EOCD central-directory offset fixture and self-test output so reviewer queues can see why a corrupt DOCX/EPUB/ODT-style package was blocked without invoking external archive tools.

## Verification

- Red-first focused probe after adding the test:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: failed as expected with `Call to undefined method PortLibs\Pandoc\ZipPackage::endOfCentralDirectoryOffsetPreflight()`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2615 assertions, 0 failures`
  - Delta from the prior ZIP package focused run: `+1` PHP PASS line, `+29` focused assertions.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- Syntax and JSON:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - `jq empty lanes/pandoc/lane-status.json`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP EOCD parsing,
central-directory signature recognition, raw strict ZIP import aggregation,
in-memory ZIP fixtures, the focused PHP test runner, and the lane-local
WordPress ZIP package preflight example. It did not run Pandoc, Cabal/Haskell
runners, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, tar, gzip, LZ4,
external archive repair tools, external converters, online services, live
provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat accepted central-directory inventory entry-count mismatch,
recoverable central-header repair-plan, duplicate central-directory name,
duplicate local-header offset, central/local metadata mismatch, ZIP64 EOCD,
ZIP64 extra-field, data-descriptor, trailing EOCD byte, package-prefix,
archive-extra-data, platform sidecar, Unicode path/comment extra-field, or
external-attribute policy work. It only adds raw EOCD central-directory pointer
diagnostics for the case where EOCD exists but points the central directory at
non-central bytes.

## Next

Good follow-ups are ZIP64 package-level instantiation strategy, central-directory
locator consistency, remaining data-descriptor edge diagnostics, or DOCX/EPUB/ODT
reader consumption of raw strict ZIP diagnostics while staying native PHP and
external-tool free.
