# ZIP Package Core Current-Base Slice

Date: 2026-06-09 UTC

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T012742Z`

Accepted base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Behavior

- Added bounded ZIP entry-name hygiene detection for Unicode format-control characters in package part names.
- `ZipPackage::nameHygienePreflight()` now reports per-entry `unicodeFormatControlEntryCount`, `unicodeBidiControlEntryCount`, and per-segment control names such as `right-to-left-override`, `left-to-right-mark`, and `zero-width-joiner`.
- Strict Office/EPUB/ODT package import continues to use the existing `name-hygiene-review-entries` diagnostic, so normal ZIP reads remain available for reviewer inspection while strict media handoff blocks invisible/spoofing path names.
- Extended the WordPress ZIP package preflight example with bidirectional override and zero-width joiner media-name fixtures.

## Verification

- Baseline focused test before this slice: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2072 assertions, 0 failures`.
- Red-first after adding the focused test before implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2074 assertions, 1 failures`; the new Unicode format-control entry count was `0` instead of `3`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 2107 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `ZipPackage` path/name hygiene preflight, strict import aggregation, focused PHP ZIP fixtures, and the lane-local WordPress ZIP package preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted central-directory signature, stored-first mimetype descriptor policy, central-directory local-header offset diagnostics, trailing-deflate payload integrity, Unicode normalization collision, invalid DOS timestamp, ZIP64, split archive, archive extra-data record, encryption, unsupported compression, local-header name/metadata mismatch, data-descriptor integrity, or central/local extra-field mismatch behavior. It is limited to Unicode format-control name hygiene for ZIP package part names before DOCX/EPUB/ODT media handoff.

## Next

Pick a non-overlapping ZIP/package primitive such as remaining ZIP64/data-descriptor edge diagnostics, central-directory recovery metadata, or DOCX/EPUB/ODT reader handoff behavior that consumes these strict package preflights.
