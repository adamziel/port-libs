# DOCX/OpenXML XML Namespace Shadow Provenance

Slice: `plib-x8y50`, DOCX OpenXML package ingestion.

## Change

- `DocxOpenXmlReader` now records metadata-only namespace shadow provenance for XML-inspectable DOCX package parts.
- Per-part inventory rows carry namespace prefix rebinding versus compatible redeclaration counts, current and previous namespace URI buckets, element paths/names, and per-shadow review rows.
- `packageProvenance.summary` rolls those rows up across the package without exposing XML element text or package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`

Post-rebase focused DOCX validation passed with 3 files, 12830 assertions, and 0 failures.

No Pandoc, office suites, TeX/browser engines, zip/unzip, ZipArchive, external validators, online services, live provider tests, or live-service provider tests were invoked.
