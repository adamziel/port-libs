# DOCX/OpenXML ZIP comment control provenance - 2026-06-30

Slice: `plib-mrlie`, DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now carries metadata-only ZIP package and entry comment diagnostics into `packageProvenance.summary`. The summary exposes package comment flags, lengths, encodings, issue codes, Unicode format-control and bidi names, commented entry names, control/unicode/bidi entry lists, and review rows already produced by `zipPackage.comments`.

The slice keeps ZIP comment text blocked. Package and entry comment bodies are not exposed through package provenance, summary rows, part inventory rows, or JSON review output.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case and expands the existing ZIP comment case with 167 assertions. Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 13,739 assertions, 0 failures after rebase)

Lane accounting:

- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `2,457 -> 2,458`
- Added `mappedDocxZipCommentControlProvenanceCases: 1`
- Added `docxZipCommentControlProvenanceAssertions: 167`

No Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
