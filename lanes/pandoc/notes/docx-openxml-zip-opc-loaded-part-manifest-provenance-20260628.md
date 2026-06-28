# DOCX OpenXML ZIP OPC Loaded Part Manifest Provenance

Date: 2026-06-28

DocxOpenXmlReader now projects metadata-only OPC ZIP manifest classification onto loaded DOCX package inventory rows. Loaded parts carry manifest entry names, OPC part names, roles, handoff kinds, relationship-source diagnostics, issue codes, and exact size metadata when available, while `packageProvenance.summary` reports loaded-part role, handoff-kind, relationship-part, content-types item, and issue rollups.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 11599 assertions, 0 failures
