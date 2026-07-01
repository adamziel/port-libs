# pandoc-docx-openxml-macro-template-target-suffix-rollups-20260701

Slice: `pandoc-docx-openxml-macro-template-target-suffix-rollups-20260701`

## Scope

This bounded DOCX/OpenXML package-ingestion slice adds package-level target
suffix rollups for metadata-only macro and attached-template relationships.
Individual `relationshipInventorySummary()` rows already retained query and
fragment suffixes; this slice exposes aggregate review fields so import reports
can flag suffix-bearing macro/template package edges without scanning every
item.

## Implemented

- `vbaProjects` now reports distinct target reference suffixes for VBA project
  relationships, nested project-signature relationships, and nested VBA data
  relationships.
- `packageProvenance.summary` mirrors those rollups as
  `vbaProjectTargetReferenceSuffix*`,
  `vbaProjectSignatureTargetReferenceSuffix*`, and
  `vbaDataTargetReferenceSuffix*` fields.
- `attachedTemplates` now reports distinct target reference suffixes across
  referenced settings entries and unreferenced attached-template relationships.
- `packageProvenance.summary` mirrors attached-template suffix rollups as
  `attachedTemplateTargetReferenceSuffix*` fields.

## Boundaries

The slice does not expose macro/template bytes, execute macros, open attached
templates, invoke Pandoc or office suites, call zip/unzip, use browser engines,
run external validators, or fetch remote relationship targets. It is
metadata-only package provenance.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 12528 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*Test.php`
  - `77 test files, 16988 assertions, 0 failures`
