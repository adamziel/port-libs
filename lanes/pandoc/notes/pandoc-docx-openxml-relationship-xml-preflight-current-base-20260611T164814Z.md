# DOCX OpenXML relationship XML preflight

Bead: `plib-2fp3t`

This slice keeps the existing native PHP DOCX/OpenXML reader path and adds package-ingestion provenance for malformed relationship XML records. Each DOCX package relationship part now exposes a `relationshipXmlPreflight` summary with record counts, invalid record counts, duplicate relationship IDs, issue counts, visible Id/Type/Target/TargetMode attributes, and duplicate-origin ordinals. The package summary also rolls up invalid relationship part counts, invalid relationship record counts, relationship issue counts, affected relationship part names, and duplicate IDs.

The focused fixture covers duplicate relationship IDs, missing Type/Target attributes, invalid XML ID shape, and non-canonical TargetMode casing without shelling out to Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, or live provider tests.

Verification:
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- Focused result: 1 test file, 573 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
- Full result: 44 test files, 64024 assertions, 0 failures.
