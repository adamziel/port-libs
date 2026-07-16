# ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T041826Z`

Accepted base: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

## Behavior

- Extended `ZipPackage::commentPreflight()` with Unicode format-control and bidi-control metadata for both package comments and per-entry comments.
- Strict ZIP import preflight now emits `comment-unicode-format-controls` and `comment-bidi-format-controls` diagnostics when comment metadata contains invisible or direction-changing Unicode controls.
- The WordPress ZIP package preflight example now self-tests a package comment containing right-to-left override metadata and an entry comment containing zero-width joiner metadata.

## Verification

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 2422 assertions, 0 failures`
  - Added focused coverage: 1 PHP PASS line / 35 assertions for ZIP comment Unicode-control metadata.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage` comment decoding, existing Unicode format-control scanners, strict import preflight aggregation, in-memory ZIP fixtures, and the WordPress ZIP preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted stored-first mimetype descriptor policy, central-directory local-header offset diagnostics, entry-name Unicode hygiene, entry-count mismatch direction, external-attribute policy, ZIP64 end/extra-field reporting, split archive detection, data descriptor integrity, unsupported compression/encryption policy, local-header metadata mismatch, central-directory signatures, archive extra-data records, or Unicode path/comment extra-field policy. It is limited to ZIP package and entry comment Unicode-control review metadata for strict package handoff.

## Next

Good follow-ups are remaining ZIP64/data-descriptor edge diagnostics, DOCX/EPUB/ODT reader consumption of raw strict preflight diagnostics, or central-directory recovery policy as separate native PHP slices.
