# DOCX numbering relationship target path provenance - 2026-07-01

Slice: `plib-d3q`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries metadata-only target path provenance for
document-level numbering relationships. Each numbering relationship review row
includes target directory, directory basename, basename, basename stem, and
path segment fields. The package summary exposes compact target directory,
basename, and path-segment rollups.

External numbering targets stay metadata-only: they are not fetched, remain out
of local target path buckets, and continue to use the existing external target
policy metadata.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 15,717 assertions, and 0 failures.

Accounting:

- `mappedDocxNumberingRelationshipTargetPathCases`: 1
- `docxNumberingRelationshipTargetPathAssertions`: 44
- `benchmarkDenominator.mapped`: 2,464 -> 2,465
