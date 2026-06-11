# DOCX Relationship Duplicate Id Provenance

Slice: `pandoc-docx-openxml-relationship-duplicate-id-provenance-20260611T220642Z`

Current base: `origin/main` at `71ce25fbe`.

## Summary

`DocxOpenXmlReader` now preserves raw relationship record provenance for DOCX
`.rels` parts alongside the normal unique relationship map. Duplicate
`Relationship` `Id` collisions stay visible through per-part
`relationshipRecords`, duplicate ID rollups, XML issue records, and package
summary metadata for review handoff.

The reader still uses the bounded native relationship map for actual DOCX
package resolution, while review queues can inspect the hidden duplicate record
that would otherwise be overwritten by the final ID entry.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
  - no syntax errors.
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1034 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66716 assertions, 0 failures.

No Pandoc executable, Word, LibreOffice, office suite, zip/unzip command,
browser renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
