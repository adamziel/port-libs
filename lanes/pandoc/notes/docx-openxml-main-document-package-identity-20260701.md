# DOCX/OpenXML main document package identity

## Scope

- Added a bounded package-ingestion identity summary for the DOCX main document part.
- The summary classifies the main document content type as document, template, macro-enabled document, macro-enabled template, missing, or unknown.
- Exposed the identity on `docx.documentPackageIdentity`, `packageProvenance.documentPackageIdentity`, and package summary fields for reviewer handoff.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`

No external Pandoc, office-suite, TeX/browser-engine, unzip/zip, Jupyter, Node, or external-validator tooling was used.
