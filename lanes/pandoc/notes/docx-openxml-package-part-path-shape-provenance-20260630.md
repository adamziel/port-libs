# DOCX OpenXML package part path-shape provenance

Slice: `plib-wpug9`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries normalized package part path-shape metadata through `packageProvenance.parts` and summary rollups. The new metadata classifies content-types items, package root relationships, relationship sidecars, relationship directories, root-level versus nested parts, single versus multi-segment paths, and extensionless versus extensioned package parts.

The slice is metadata-only package ingestion. It does not expose package payload bytes beyond the existing bounded inventory sizes and hashes, and it does not shell out to Pandoc, office tooling, zip/unzip, browsers, TeX, Node, or external validators.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> `1 test files, 9979 assertions, 0 failures`

Direct-format parity remains active in `lane-status.json`; this slice adds DOCX/OpenXML package-ingestion provenance and does not change the direct format denominator.
