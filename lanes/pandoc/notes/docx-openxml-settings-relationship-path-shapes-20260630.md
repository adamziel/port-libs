# DOCX OpenXML Settings Relationship Path Shapes

Date: 2026-06-30
Bead: plib-cnuxr

## Scope

- Added metadata-only settings relationship rollups for parent-traversal targets, package-root targets, and same-source-part targets.
- Mirrored those rollups into `packageProvenance.summary` for importer review handoff.
- Covered internal settings sidecars plus safe and unsafe external settings relationships without fetching external targets or exposing package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Result: 1 test file, 10,024 assertions, 0 failures.

## Accounting

- Focused PHP behavior tests: 469 -> 470.
