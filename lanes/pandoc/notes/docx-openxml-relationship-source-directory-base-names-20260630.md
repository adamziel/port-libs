# DOCX/OpenXML relationship source directory base names

Slice: `plib-foq87` / DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now reports `packageProvenance.summary.relationshipSourceDirectoryBaseNames`, a metadata-only inventory of relationship source directory basename buckets. Each bucket groups DOCX relationship sidecar sources by directory basename and carries source counts, existing/non-existing counts, unique source directories, relationship source kinds, source base names, extensions, content types, roles, relationship sidecar parts, and the largest existing source record with digest metadata.

The inventory is derived from already-loaded relationship source rows. It does not fetch external relationship targets, expose package bytes, or invoke office/Pandoc/unzip tooling.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: 1 file, 10,011 assertions, 0 failures.

Direct-format parity remains active: this closes a bounded DOCX/OpenXML package provenance gap and does not claim full DOCX conversion parity or external target resolution.
