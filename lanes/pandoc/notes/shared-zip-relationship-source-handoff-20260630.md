# Shared ZIP Relationship Source Handoff

`ZipPackage::entryHandoffPreflight()` now carries metadata-only OPC
relationship source provenance for selected ZIP entries before reader byte
handoff.

## Scope

- Adds per-entry `isRelationshipPart`, `relationshipPartName`,
  `relationshipSourcePartName`, `relationshipSourceDirectory`, and
  `relationshipSourceScope` fields.
- Adds selected/readable relationship-source summaries so DOCX/EPUB/ODT
  package readers can audit `_rels/.rels` and `*/_rels/*.rels` package areas
  before exposing relationship XML bytes.
- Keeps blocked oversized relationship parts out of readable handoff source
  buckets.

## Accounting

- `lane-status.json` `phpPass`: `473 -> 474`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2317 -> 2318`
- Added `sharedZipSelectedHandoffRelationshipSourceCases: 1`
- Added `mappedSharedZipSelectedHandoffRelationshipSourceCases: 1`

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,870 assertions, 0 failures
