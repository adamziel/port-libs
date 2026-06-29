# DOCX/OpenXML package path segment character provenance

Slice: `plib-q26em`, Pandoc DOCX OpenXML package ingestion core blocker, 2026-06-29.

`DocxOpenXmlReader` now reports metadata-only package path-segment character provenance for DOCX package parts. The package inventory exposes per-part `pathSegmentCharacterReviews`, stable flag counts, and booleans for uppercase, whitespace, percent-encoded octets, and non-ASCII path segments. `packageProvenance.summary` rolls those rows up into segment counts, part-name buckets, and segment buckets so import review can identify the exact path segment that triggered review without exposing package bytes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 13,010 assertions, 0 failures.

No Pandoc, office suite, zip/unzip, browser, TeX, Node, external validator, or external fetch was invoked.
