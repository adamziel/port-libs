# DOCX OpenXML macro/template package provenance

Slice: `plib-sdfqv`, DOCX OpenXML package ingestion core blocker.

Implemented one bounded native PHP package-ingestion slice:

- `DocxOpenXmlReader` now exposes `docx.macroProjects` for document-level
  `vbaProject` relationships without parsing or executing macro bytes.
- Macro summaries include relationship id/source, target/resolved target,
  query/fragment suffix, package part name, byte length, content type/source,
  default/override provenance, and missing/external/unexpected-type diagnostics.
- `DocxOpenXmlReader` now exposes `docx.attachedTemplates` and mirrors the first
  item as `docx.settings.attachedTemplate` when `w:settings/w:attachedTemplate`
  references settings-part relationships.
- Attached-template summaries preserve settings relationship sidecar provenance,
  internal package target metadata, and external-template diagnostics without
  fetching template targets.
- Package-wide relationship-part and relationship-type inventory continues to
  index the new settings sidecar and target roles.

Verification on rebased target `origin/main` `2cea4fa78`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 659 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files,
  64451 assertions, 0 failures.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
