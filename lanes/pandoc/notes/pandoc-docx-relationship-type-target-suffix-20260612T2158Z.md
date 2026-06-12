# Pandoc DOCX Relationship-Type Target Suffix Slice

Date: 2026-06-12
Bead: plib-zzv1p
Base: origin/main 58011902

## Scope

DOCX/OpenXML package relationship-type review buckets now preserve target
suffix provenance for each grouped relationship entry. `DocxOpenXmlReader`
already calculated this data for package relationship inventories; this slice
keeps `resolvedTarget`, `targetQuery`, `targetFragment`,
`targetReferenceSuffix`, `defaultExtension`, and `overridePartName` available
under `docx.packageProvenance.relationshipTypes[*].relationships`.

This keeps WordPress import review packets from losing query/fragment context
when relationships are grouped by type for package review. No Pandoc, Word,
LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are invoked.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 2301 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73809 assertions, 0 failures`

## Accounting

- `phpPass`: `3288 -> 3289`
- `phpFail`: remains `0`
- Static mapped denominator: `3249 -> 3250`
- Added `mappedDocxRelationshipTypeTargetSuffixCases = 1`
- Added `docxRelationshipTypeTargetSuffixAssertions = 35`

## Non-Overlap

This does not repeat accepted package relationship inventories, selected
settings/font-table relationship provenance, package target summaries, header and
footer part summaries, or content-type collision diagnostics. It is limited to
relationship-type bucket entries retaining target suffix and content-type source
provenance.
