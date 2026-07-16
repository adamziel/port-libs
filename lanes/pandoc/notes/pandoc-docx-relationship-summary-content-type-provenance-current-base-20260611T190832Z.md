# pandoc-docx-relationship-summary-content-type-provenance-current-base-20260611T190832Z

Slice: `plib-wkp1b`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `caf9a25cb`.

## Scope

DOCX relationship inventory already computed target suffix details and
content-type provenance for each OpenXML relationship target. This slice carries
that already-computed provenance into the compact package summary surfaces used
for review handoff.

## Change

`DocxOpenXmlReader` now preserves relationship target query and fragment
metadata, content-type base, parameter details, default extension, and override
part provenance in:

- `summary.missingRelationshipTargets`
- `summary.relationshipTargetsWithoutContentType`
- `packageProvenance.relationshipTypes[*].relationships`

The change does not alter package parsing, relationship resolution, ZIP
handling, or DOCX rendering behavior.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 822 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65491 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
