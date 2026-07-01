# DOCX OpenXML package identity extension normalization

Slice: `plib-dggzb`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries normalized raw-extension case-variant metadata through `packageProvenance.packageIdentity` and mirrors the identity fields in `packageProvenance.summary`. Reviewers can compare package identities for uppercase or otherwise normalized part extensions without walking the full extension bucket inventory.

The slice is metadata-only package ingestion. It does not expose package payload bytes beyond the existing bounded inventory sizes and hashes, and it does not shell out to Pandoc, office tooling, zip/unzip, browsers, TeX, Node, or external validators.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> `1 test files, 16008 assertions, 0 failures`

Direct-format parity remains active in `lane-status.json`; this slice adds DOCX/OpenXML package-ingestion provenance and does not change the direct format denominator.
