# DOCX/OpenXML Namespace Declaration Depth Provenance - 2026-06-28

Slice: `plib-qd82g`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries metadata-only XML namespace declaration owner-depth provenance for XML-inspectable DOCX package parts. Per-part inventory rows and `packageProvenance.summary` include declaring element depth counts and compact depth lists, and each namespace declaration row already carries its owner `elementDepth`.

The slice keeps raw XML text and package bytes unexposed. Namespace URI byte lengths and digests remain metadata-only; no external namespace URI is fetched or validated.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 11,808 assertions, and 0 failures.

No Pandoc, Word, LibreOffice, office suites, TeX/PDF engines, browser renderers, zip/unzip, ZipArchive, external validators, online services, live provider tests, or live-service provider tests were invoked.
