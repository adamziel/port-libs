# DOCX OpenXML XML Attribute Value Shapes

Date: 2026-06-28

DocxOpenXmlReader now carries metadata-only XML element-attribute value-shape provenance for XML-inspectable DOCX package parts. Package inventory rows and `packageProvenance.summary` report empty, whitespace-only, non-whitespace, edge-whitespace, line-break, and token counts while keeping raw attribute values and package bytes unexposed.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 11545 assertions, 0 failures
