# DOCX Relationship Path Byte-Length Buckets

## Context

DOCX package provenance already summarized package part path byte-length buckets, but relationship source and target paths did not expose the same compact signal. That left package review handoff without a quick way to spot unusually long relationship references.

## Change

- Added relationship source path byte-length bucket summaries to `DocxOpenXmlReader`.
- Added internal relationship target path byte-length bucket summaries with target existence, content type source, relationship type, role, and longest-path details.
- Reused the existing package path byte-length bucket thresholds: up to 8 bytes, 9-16 bytes, 17-32 bytes, 33-64 bytes, and over 64 bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlRelationshipPathByteLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipPathByteLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePathByteLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipSourcePathSegmentsTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetPathSegmentPositionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationship*Test.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
