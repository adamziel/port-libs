# Pandoc Legacy DOC/CFB CHPX Picture Data-stream Metadata

Slice: pandoc-legacy-doc-cfb-core-current-base-20260608T233603Z
Base accepted HEAD: 9ded36a0bdf8a38d0d938423ba129d62e7355cba

## Behavior

- Parses CHPX `sprmCFSpec` (0x0855), `sprmCPicLocation` (0x6a03), and `sprmCFData` (0x0806) from ChpxFkp runs.
- Maps WordDocument file offsets back to visible CP ranges for direct FIB text and CLX piece-table text.
- Links inline picture `U+0001` placeholders to the CFB `Data` stream offset/count when CHPX metadata matches, while preserving `canExposeBytes=false`.
- Keeps the existing generic `fib-has-pictures` fallback when no CHPX picture-location metadata matches the visible placeholder.

## Source Truth

- MS-DOC character properties define `sprmCFSpec`, `sprmCPicLocation`, and `sprmCFData`.
- MS-DOC picture object storage points picture payload records into the `Data` stream. This slice preserves safe metadata only and does not decode PICF/OfficeArt payload bytes.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 1818 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 1843 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed.
- Final lint/diff checks: `php -l` changed PHP files passed, JSON status/manifest decode passed, and `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB reader, LegacyDocReader FIB/piece-table/ChpxFkp metadata parsing, and WordPress block/span handoff. PICF/OfficeArt payload decoding and actual image extraction remain follow-up, not blockers for this metadata-only slice.
