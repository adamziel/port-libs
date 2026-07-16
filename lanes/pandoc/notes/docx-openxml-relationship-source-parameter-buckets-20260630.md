# DOCX/OpenXML relationship source parameter buckets - 2026-06-30

Slice: `plib-6yqsz`, DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now exposes source-side content type parameter buckets for
relationship parts. The package provenance summary keeps parameter-name rows
with source counts, source relationship kinds, content type bases/sources, roles,
relationship part names, source part names, value counts, byte totals, and the
largest existing source part.

This reuses already parsed OPC relationship-source provenance and does not
expose package bytes, call Pandoc, use office suites, shell out to zip/unzip, or
invoke external validators.

Validation:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
