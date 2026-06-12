# Pandoc ODT Package Font Provenance

Area: Pandoc ODF/ODT OpenDocument package ingestion

## Slice

`OdfReader` now classifies packaged font resources as metadata-only package review items:

- declared `Fonts/*` entries;
- font media types outside `Fonts/`;
- missing font parts;
- encrypted font parts;
- undeclared font ZIP entries;
- invalid media types under `Fonts/`.

Package fonts are surfaced through `packageFonts`, document `odfPackageFonts` metadata, and import-report review rows. Package inventory now adds `font-package` role counts and per-part byte-exposure policy. Font resources stay out of document media handoff and use `font-package-bytes-blocked` unless encryption or another stricter package policy applies.

This does not load fonts, parse font binaries, decrypt packages, invoke Pandoc, invoke office suites, call zip/unzip, use browser renderers, call external validators, or use online services.

## Accounting

- `phpPass`: `3193 -> 3194`
- `phpFail`: `0`
- `mappedOdtReaderPackageFontProvenanceCases`: `1`
- `odtReaderPackageFontProvenanceAssertions`: `52`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 4242 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 70667 assertions, 0 failures
