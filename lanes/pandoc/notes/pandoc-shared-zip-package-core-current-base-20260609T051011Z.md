# Pandoc Shared ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T051011Z`

Accepted base: `516b4c2368ab923eeb7c71f762618468a7a4d437`

## Behavior

- Added `ZipPackage::centralDirectoryRepairPlanPreflight()` for archives whose EOCD `centralDirectorySize` understates the central-directory byte span while complete central-directory headers remain before EOCD.
- The preflight is review-only and non-instantiating. It reports retained entries, recoverable gap entries, planned entries, corrected central-directory size, recovered and unrecovered gap bytes, duplicate planned decoded-name/raw-name/local-header-offset groups, and whether a complete repair plan is available.
- `ZipPackage::rawStrictImportPreflight()` now includes the repair plan as `centralDirectoryRepairPlan` and emits repair diagnostics such as `central-directory-repair-plan-review`, `central-directory-size-understatement-repair-available`, and `central-directory-repair-gap-unrecovered`.
- Corrupt ZIP-backed DOCX/EPUB/ODT packages remain blocked by the bounded reader; this only gives WordPress import review queues a precise repair handoff instead of requiring `zip`, `unzip`, `ZipArchive`, or external archive repair tools.

## Verification

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2534 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2586 assertions, 0 failures`
  - Delta: `+1` PHP PASS line, `+52` focused assertions.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- Syntax:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP EOCD parsing,
central-directory inventory scanning, duplicate-group helpers, raw strict ZIP
import aggregation, in-memory ZIP fixtures, and the lane-local WordPress ZIP
package preflight example. It did not run Pandoc, Cabal/Haskell runners, Word,
LibreOffice, `zip`, `unzip`, `ZipArchive`, tar, gzip, LZ4, external archive
repair tools, external converters, online services, live provider tests, or
live-service provider tests.

## Non-Overlap

This does not repeat accepted ZIP64 extra-field reporting, ZIP64 EOCD
accounting, data-descriptor integrity, duplicate central-directory name
blocking, duplicate local-header offset blocking, generic central-directory gap
metadata, platform sidecar policy, Unicode path/comment extra-field policy,
comment Unicode-control review, package-prefix policy, split archive policy, or
external-attribute symlink/special-file policy. It only adds a review-only
repair-plan summary for recoverable central-directory headers after an
understated EOCD central-directory size.

## Next

Good follow-ups are DOCX/EPUB/ODT reader consumption of raw strict ZIP
diagnostics, remaining data-descriptor edge diagnostics, or bounded ZIP64
accounting/reporting that stays external-tool free.
