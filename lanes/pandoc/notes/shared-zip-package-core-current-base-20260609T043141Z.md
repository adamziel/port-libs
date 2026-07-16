# ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T043141Z`

Accepted base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Behavior

- Added `ZipPackage::platformMetadataPolicyPreflight()` as a raw central-directory scanner for macOS and Windows platform sidecar entries.
- Wired the raw policy into `ZipPackage::rawStrictImportPreflight()` so sidecars are still reported when the archive cannot instantiate because of a separate local-header problem.
- Kept the existing instance-level `platformMetadataPreflight()` shape intact by sharing only the name classification logic.
- Updated the WordPress ZIP package preflight example with a malformed package that contains `__MACOSX/word/media/._review.png` and `word/media/Thumbs.db` sidecars.

## Verification

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before patch: `1 test files, 2438 assertions, 0 failures`
- Focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result after patch: `1 test files, 2477 assertions, 0 failures`
  - Added focused coverage: 1 PHP PASS line / 39 assertions for raw ZIP platform metadata policy.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage` central-directory parsing, raw strict import aggregation, in-memory ZIP fixtures, and the WordPress ZIP preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted instance-level platform metadata blocking, external-attribute symlink/special-file policy, Unix UID/GID owner extra fields, ZIP64 extra field reporting, central-directory signatures, archive extra-data records, comment Unicode-control review, local-header name mismatch detection, split archive detection, unsupported compression/encryption policy, or Unicode path/comment extra-field policy. It is limited to raw central-directory platform sidecar policy and raw strict preflight aggregation when package instantiation fails.

## Next

Good follow-ups are DOCX/EPUB/ODT reader consumption of raw strict ZIP diagnostics, remaining ZIP64/data-descriptor edge diagnostics, or central-directory recovery policy as separate native PHP slices.
