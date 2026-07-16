# DOCX/OpenXML Document Background Image Provenance - 2026-06-15

## Scope

- Bead: `plib-41dju`
- Slice: `pandoc-docx-openxml-document-background-image-provenance`
- Lane: `pandoc`
- Constraint: native PHP only. No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Implementation

- `DocxOpenXmlReader` now inspects `w:background` VML fill image references from the main document package part.
- The package provenance preserves background color, VML fill authoring fields, relationship IDs, target query/fragment suffixes, content-type parameter provenance, package byte/CRC/SHA metadata for present internal image targets, and diagnostics for missing, external, wrong-type, and unexpected content-type references.
- Package summary rollups expose document background image counts, relationship counts, existing/missing/external counts, and issue-code buckets for review handoff.

## Coverage

- Added one focused DOCX/OpenXML package-ingestion case:
  - `summarizes docx document background image relationships for review handoff`
- Counter updates:
  - `phpPass`: `3714 -> 3715`
  - `phpFail`: `0`
  - upstream mapped cases: `3736 -> 3737`
  - `mappedDocxOpenXmlDocumentBackgroundImageCases`: `1`
  - `docxOpenXmlDocumentBackgroundImageAssertions`: `63`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 2953 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88050 assertions, 0 failures
- PHP JSON manifest/status validation
- `git diff --check`
- conflict-marker scan
