# DOCX OpenXML XML ancestor-chain provenance

Slice: `plib-8xlzi`

Adds metadata-only XML element ancestor-chain provenance for DOCX package parts that are already XML-inspectable. The reader now records per-element ancestor depth, ancestor path, parent/root qualified names, ancestor qualified-name lists, and package-level rollups without exposing XML text, attribute values, or package bytes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php` (`12533` assertions, `0` failures)
