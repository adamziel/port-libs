# Pandoc ZIP package core current-base handoff

Slice: `pandoc-shared-zip-package-core-current-base-20260606T103504Z`

Accepted base: `4d7229bc3c8e868b129629e7dc6a1682afd2bc3c`

## Behavior

Implemented one bounded ZIP package primitive for Office/EPUB/ODT package preflight: case-insensitive entry-name collision keys now canonicalize Latin Unicode names before strict extraction checks. A package containing both `word/media/Café.PNG` and canonically equivalent decomposed `word/media/café.png` is rejected by strict preflight as a case-insensitive extraction collision, while exact entry reads remain addressable by the original source names.

The implementation uses `Normalizer::normalize(..., Normalizer::FORM_C)` when PHP intl is present, with bounded Latin composition/case-fold fallbacks for common precomposed media-name characters when intl is unavailable. It does not call Pandoc, Cabal, Haskell runners, zip/unzip, ZipArchive, Word, LibreOffice, external archive tools, online services, or live provider tests.

## Source Truth

This extends the already accepted ZIP/OPC package primitive behavior in `ZipPackage::caseInsensitiveNamePreflight()` and follows the lane note left by the prior trailing-deflate ZIP slice, which named Unicode case folding beyond ASCII as the next bounded package-preflight gap. The port contract is the support-library preflight behavior needed before DOCX, EPUB, and ODT readers expose archive media bytes to WordPress import/review paths.

## Evidence

Baseline:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 752 assertions, 0 failures`

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 775 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`

Status delta: +1 focused PHP PASS case, +23 focused assertions, manifest mapped ZIP package core support cases `22 -> 23`, and `phpPass` `1298 -> 1299`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `ZipPackageEntry`, PHP intl `Normalizer` when available, bounded fallback maps, and the existing focused PHP test harness. Full ZIP64 large-archive support, encrypted/AES payload handling, cryptographic signature verification, non-deflate decompression, broader filesystem-specific Unicode equivalence, and reader-level wiring of strict preflight remain separate bounded follow-up work.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory signature provenance, NTFS extra-field timestamp preflight, Unix symlink rejection, drive-letter path rejection, ZIP64 extra-field rejection, or raw-deflate trailing-byte validation. It only adds canonical Unicode name collision handling for strict package extraction preflight.
