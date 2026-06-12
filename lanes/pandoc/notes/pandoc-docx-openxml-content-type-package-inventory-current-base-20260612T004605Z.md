# Pandoc DOCX/OpenXML content-type package inventory current base 20260612T004605Z

## Scope

This slice is limited to DOCX/OpenXML package ingestion provenance in
`DocxOpenXmlReader`. It does not invoke Pandoc, Word, LibreOffice, office
suites, zip/unzip, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

## Behavior

`document->attr('docx')['packageProvenance']['summary']` now includes:

- `partContentTypeCount`
- `parameterizedContentTypePartCount`
- `partContentTypes`

Each `partContentTypes` row groups physical package inventory entries by
normalized `contentTypeBase` and carries:

- part count and byte total
- relationship-part count
- missing-content-type part count
- parameterized content-type part count
- content-type source counts
- package role counts
- exact content-type variants
- default extensions
- override part names
- package part names

This gives review queues a compact content-type inventory without losing exact
default/override or parameterized content-type provenance.

## Red First

`php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` failed
after adding the focused expectation because `partContentTypes` and
`partContentTypeCount` were absent from the DOCX package summary.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 1396 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68198 assertions, 0 failures`

## Status Delta

- Added one focused `DocxOpenXmlReaderTest` case.
- Added 31 focused assertions.
- `phpPass`: `3156 -> 3157`
- Added lane counters:
  - `mappedDocxOpenXmlContentTypeSummaryCases`: `1`
  - `docxOpenXmlContentTypeSummaryAssertions`: `31`
