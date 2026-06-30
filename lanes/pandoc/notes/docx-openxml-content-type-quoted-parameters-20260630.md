# DOCX OpenXML Content-Type Quoted Parameters - 2026-06-30

Slice: `plib-zqsel`, DOCX/OpenXML package ingestion core blocker.

## Summary

`DocxOpenXmlReader` now parses package content-type parameter lists with quoted
semicolon support. DOCX package provenance preserves values such as
`profile="custom;review"` and escaped quoted-pair values across:

- `[Content_Types].xml` override provenance;
- relationship target summaries;
- loaded package part inventory;
- relationship-type parameterized target rollups.

The fix stays native PHP and metadata-only. It does not invoke Pandoc, Word,
LibreOffice, office suites, zip/unzip, browser engines, Node tooling, online
services, or external validators.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 9957 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `468 -> 469`
- Direct-format parity remains active; this is a bounded DOCX package-ingestion
  provenance slice, not a full direct DOCX reader parity claim.
