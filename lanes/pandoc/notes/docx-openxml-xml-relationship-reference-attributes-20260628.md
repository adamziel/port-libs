# DOCX OpenXML XML Relationship Reference Attributes

Date: 2026-06-28

This slice adds metadata-only package provenance for relationship-reference attributes in XML-inspectable DOCX parts. The reader now records `r:*` attributes such as `r:id`, `r:embed`, and `r:link`, resolves their values against the source part relationship sidecar, and summarizes matched, missing, internal, external, existing-target, missing-target, and missing-content-type states.

The metadata is exposed through each package inventory row and `packageProvenance.summary` as sorted review records and aggregate buckets. XML text, attribute payload text outside relationship IDs, target bytes, and external targets remain unexposed and unfetched.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 file, 11,741 assertions, 0 failures
