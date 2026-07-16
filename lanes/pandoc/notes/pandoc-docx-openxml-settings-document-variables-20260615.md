# DOCX OpenXML Settings Document Variables

Date: 2026-06-15
Base: a3157344c6

This slice extends native PHP DOCX/OpenXML package ingestion for settings
document variables without invoking Pandoc, Word, LibreOffice, office suites,
zip/unzip, ZipArchive, browser renderers, Node tooling, external validators,
online services, live provider tests, or live-service provider tests.

## Behavior

- `DocxOpenXmlReader` now preserves ordered `w:docVar` review records from the
  relationship-selected settings part.
- Existing `settings.documentVariables` remains a name/value map for compatible
  consumers, with later duplicate names winning as before.
- New `settings.documentVariableDetails` exposes ordered records with value
  length, SHA-256 hash, duplicate-name diagnostics, and missing-name
  diagnostics.
- Package provenance summary now reports document-variable counts, duplicate
  names, empty-name counts, and issue-code rollups.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 2931 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88508 assertions, 0 failures.

## Accounting

- `phpPass`: 3729 -> 3730
- `phpFail`: 0
- mapped upstream cases: 3747 -> 3748
- `mappedDocxOpenXmlSettingsDocumentVariableCases`: 1
- `docxOpenXmlSettingsDocumentVariableAssertions`: 41
