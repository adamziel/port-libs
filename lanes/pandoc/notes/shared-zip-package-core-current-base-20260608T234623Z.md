# ZIP Package Core Current-Base Slice

Date: 2026-06-08 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T234623Z`

Accepted base: `188120ad12d64170af6df8437e20fe1b6e719eb9`

## Behavior

- Added central-directory local-header offset classification to `ZipPackage::localHeaderSpanPreflight()`.
- Raw strict ZIP preflight now returns structured issue entries when a central-directory `localHeaderOffset` points inside the central directory, after the central directory, beyond the archive, to a truncated local header, or to non-local-header bytes.
- Extended the WordPress ZIP preflight example with a central-directory offset fixture so Office/EPUB/ODT package handoff reports `local-header-offset-inside-central-directory` before exposing package bytes.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2010 assertions, 0 failures`.
- Interim focused run after adding the new case: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2011 assertions, 1 failures`; the direct import path was already fail-closed, but the test still expected a local-header signature error instead of asserting the new raw strict span diagnostic.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2043 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- PHP lint: `php -l lanes/pandoc/src/ZipPackage.php`, `php -l lanes/pandoc/tests/ZipPackageTest.php`, and `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` all reported no syntax errors.
- JSON/diff checks: lane JSON decoded successfully and `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `ZipPackage` EOCD, central-directory, local-header span, raw strict preflight, and WordPress ZIP package preflight behavior. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted central-directory signature, trailing-deflate, Unicode-name collision, invalid DOS timestamp, stored-first mimetype data-descriptor, ZIP64, split archive, archive extra-data record, encryption, unsupported compression, local-header name/metadata mismatch, data-descriptor integrity, or strict import aggregate behavior. It is limited to central-directory local-header offset recovery diagnostics.

## Next

Pick a non-overlapping ZIP/package primitive such as remaining extra-field policy gaps, DOCX/EPUB/ODT package-reader handoff behavior, or ZIP64/data-descriptor edge diagnostics not already covered by the current package tests.
