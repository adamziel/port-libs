# DOCX/OpenXML relationship target path segments

Slice: `plib-7tx3l` / DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now reports `packageProvenance.summary.relationshipTargetPathSegments`, a metadata-only inventory of path tokens from internal OPC relationship targets. Each segment bucket carries occurrence and relationship counts, existing/missing target counts, missing content-type and parameterized target counts, source parts, relationship parts and ids, relationship types, content types, target directories, target roles, target parts, and the largest existing target record with digest metadata.

The inventory is derived from already-resolved internal relationship target rows. External targets remain excluded from the segment buckets, are not fetched, and package part bytes remain blocked.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: 1 file, 9,998 assertions, 0 failures.

Direct-format parity remains active: this closes a bounded DOCX/OpenXML package provenance gap and does not claim full DOCX conversion parity, external target resolution, or office-suite validation.
