# ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T002647Z`

Accepted base: `28428232606f6fb6b3df81661dee1f40b90244b3`

## Behavior

- Added `ZipPackage::endOfCentralDirectoryTrailingBytesPreflight()` to locate a plausible EOCD record even when detached bytes follow the declared ZIP archive end.
- Raw strict import preflight now reports structured `eocd-trailing-bytes` metadata before package instantiation fails closed, including EOCD offset, declared archive end, declared comment length, available comment bytes, trailing byte count, entry count, central-directory offset/size/end, and support issues.
- Extended the WordPress ZIP preflight example with a detached trailing-EOCD fixture so Office/EPUB/ODT package handoff can classify trailing bytes without exposing those bytes.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2043 assertions, 0 failures`.
- Red-first: after adding the focused EOCD trailing-byte case, `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2043 assertions, 1 failures`; failure was `Call to undefined method PortLibs\Pandoc\ZipPackage::endOfCentralDirectoryTrailingBytesPreflight()`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2068 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- PHP lint: `php -l lanes/pandoc/src/ZipPackage.php`, `php -l lanes/pandoc/tests/ZipPackageTest.php`, and `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` all reported no syntax errors.
- JSON/diff checks: lane JSON decoded successfully and `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `ZipPackage` EOCD scanning, central-directory metadata, raw strict import preflight, and the existing WordPress ZIP package preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted central-directory signature, trailing-deflate payload, Unicode-name collision, invalid DOS timestamp, stored-first mimetype data-descriptor, ZIP64, split archive, archive extra-data record, encryption, unsupported compression, local-header name/metadata mismatch, local-header span/offset diagnostics, data-descriptor integrity, or strict import aggregate behavior. It is limited to trailing bytes after EOCD.

## Next

Pick a non-overlapping ZIP/package primitive such as remaining extra-field policy gaps, ZIP64/data-descriptor edge diagnostics, or DOCX/EPUB/ODT package-reader handoff behavior.
