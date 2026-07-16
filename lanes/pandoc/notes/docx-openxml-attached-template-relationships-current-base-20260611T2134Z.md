# DOCX OpenXML Attached Template Relationships Current Base 2026-06-11T21:34Z

Slice: `docx-openxml-attached-template-relationships-current-base-20260611T2134Z`

## Scope

- Added native PHP DOCX/OpenXML package ingestion metadata for `w:attachedTemplate`
  records in `settings.xml`.
- The reader now parses the selected settings part relationship sidecar, exposes
  `docx.settingsRelationshipsPart`, `docx.settingsRelationships`, and
  `docx.attachedTemplates`.
- Attached-template summaries include referenced and unreferenced relationship
  targets, internal/external counts, missing/unknown diagnostics, target
  query/fragment suffixes, content-type parameter provenance, byte counts for
  internal package parts, and package relationship/type inventory propagation.

## Boundaries

- No Pandoc executable, Word, LibreOffice, office suite, `zip`, `unzip`,
  browser renderer, external validator, online service, live provider test, or
  live-service provider test was invoked.
- External attached-template targets are kept as inert relationship metadata and
  are not fetched.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: 1 test file, 958 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 44 test files, 66286 assertions, 0 failures.

## Accounting

- Added `mappedDocxOpenXmlAttachedTemplateCases: 1`.
- Added `docxOpenXmlAttachedTemplateAssertions: 70`.
