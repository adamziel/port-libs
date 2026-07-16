# DOCX OpenXML Selected XML Digests

Slice: `plib-0wcqk`, DOCX/OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now records CRC32 and SHA-256 byte digests for existing
  selected XML parts in `packageProvenance.selectedXmlParts`.
- The digest metadata covers both relationship-selected parts, such as the main
  document, core properties, and settings, and conventional package parts such
  as styles.
- Missing optional selected parts remain metadata-only with `bytes = 0` and
  null digests.
- Package summary now reports `selectedXmlPartByteDigestCount`.

This is native PHP package provenance only. It does not invoke Pandoc, Word,
LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Accounting

- `phpPass`: `17018 -> 17019`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `16604 -> 16605`
- Root mapped inventory: `16573 -> 16574`
- Benchmark denominator mapped cases: `3742 -> 3743`
- `mappedDocxOpenXmlSelectedXmlDigestCases = 1`
- `docxOpenXmlSelectedXmlDigestAssertions = 27`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `3989` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258` files, `175723` assertions, `0` failures
