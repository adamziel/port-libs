# DOCX/OpenXML Package Inventory SHA-256 Provenance

Slice: `plib-r1rxf`, DOCX/OpenXML package ingestion.

## Change

`DocxOpenXmlReader` now preserves SHA-256 byte provenance for DOCX package parts
alongside existing CRC32 and byte-length metadata. The hash is carried through:

- `packageProvenance.parts[*].sha256`
- `packageProvenance.summary.largestParts[*].sha256`
- `packageProvenance.summary.partsWithoutContentType[*].sha256`

This keeps reviewer queues able to compare package part bytes deterministically
without exposing blocked payloads as document media.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 1792 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70944 assertions, 0 failures` after rebase over the
    JSON/native Table/Figure sidecar-free payload slice

## Accounting

- Added one focused `DocxOpenXmlReaderTest.php` PASS case.
- Focused assertion delta: `+12`.
- `phpPass`: `3203 -> 3204`.
- `phpFail`: `0`.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were run.
