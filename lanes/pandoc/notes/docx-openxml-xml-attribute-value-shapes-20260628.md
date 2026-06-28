# DOCX/OpenXML XML Attribute Value Shapes

Slice: `plib-hbub7` recovered on top of `plib-tp80k`, DOCX/OpenXML package ingestion core blocker, 2026-06-28.

`DocxOpenXmlReader` now carries metadata-only XML element-attribute value-shape provenance for XML-inspectable DOCX package parts. Attribute records, part inventory rows, and `packageProvenance.summary` report empty, whitespace-only, non-whitespace, edge-whitespace, line-break, and token counts, plus categorical value-shape buckets for empty, token, token-list, boolean, integer, decimal, QName, relationship ID, absolute URI, network-path reference, and relative reference.

The slice keeps raw XML attribute values, XML text, comments, processing-instruction data, CDATA text, package bytes, and relationship target bytes out of review metadata.

Validation run:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php` - pass
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - pass
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - worker pass, 1 test file, 11,536 assertions, 0 failures
- Broader `php tools/run-tests.php lanes/pandoc/tests` remains baseline-red in existing unrelated YAML metadata review expectations

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, TeX/PDF engines, external validators, online services, live provider tests, or live-service provider tests are required for this slice.
