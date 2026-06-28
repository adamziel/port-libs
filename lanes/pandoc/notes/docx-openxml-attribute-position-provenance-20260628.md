# DOCX/OpenXML XML Attribute Position Provenance

Slice: `plib-4119a`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries metadata-only XML element attribute-position
provenance for XML-inspectable DOCX package parts. Part inventory rows and
package-wide rollups include per-attribute indexes, parent attribute-count
buckets, preceding/following attribute counts, and first/last/only flags.

The new review fields do not expose raw XML attribute values or package bytes.
They extend the existing XML element-attribute provenance after accepted
attribute value-shape and value-length bucket slices.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -
  1 file, 11,879 assertions, 0 failures.
