# DOCX/OpenXML Header/Footer Relationship Provenance

Bead: plib-fnivl
Base: origin/main 432cc5a6fd58
Date: 2026-06-12 UTC

This slice maps one native DOCX/OpenXML package ingestion case for header and footer relationship issue provenance. `DocxOpenXmlReader` now keeps referenced unknown ids, external header/footer targets, missing internal targets, unreferenced relationships, target suffix metadata, issue counts, and package summary counts visible for review handoff without changing rendered document content.

Accounting:
- `phpPass`: 3165 -> 3166
- `phpFail`: 0
- `mappedDocxOpenXmlHeaderFooterRelationshipIssueCases`: 1
- `docxOpenXmlHeaderFooterRelationshipIssueAssertions`: 42
- `benchmarkDenominator.mapped`: 3227 -> 3228

Verification:
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1408 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68668 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
