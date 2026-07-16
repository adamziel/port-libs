# DOCX Package-Root Relationship Metadata

Slice: `plib-4ojej` DOCX OpenXML package ingestion core blocker.
Base: current main `8d374c5c25`.

## Change

- Promoted package-root relationship role records into `docxPackageRelationships`
  metadata, not only the import report.
- Preserved compact per-relationship review fields for valid and invalid rows,
  including role, target part, content-type expectations, source policy,
  external-target policy, relationship type diagnostics, validity, and issues.
- Kept package ingestion behavior unchanged: relationship resolution still uses
  the existing import report graph and does not expose extra package bytes.

No Pandoc, Word, LibreOffice, office suites, zip/unzip tools, ActiveX runtimes,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file, 5057 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70479 assertions, 0 failures
