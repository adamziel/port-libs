# DOCX ZIP Entry Comment Inventory Summaries

Slice: `plib-smisf`
Date: 2026-07-01

## Scope

DOCX/OpenXML package ingestion now carries metadata-only ZIP entry-comment inventory rollups through `DocxOpenXmlReader` package provenance.

The new `packageProvenance.zipEntryComments` handoff and matching `packageProvenance.summary` fields group commented ZIP entries by:

- DOCX inventory role
- ZIP directory root
- package-part extension

Each rollup records comment byte lengths, CRC32/SHA-256 digests, issue codes, content-type/source buckets, role counts, part names, and largest commented part metadata. Raw entry-comment text and raw comment bytes are not exposed in the new DOCX rollup.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipEntryCommentInventorySummariesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipEntryCommentInventorySummariesTest.php`:
  1 file, 41 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlZipEntryCommentInventorySummariesTest.php`:
  2 files, 12,549 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*Test.php`:
  78 files, 17,009 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`:
  535 files, 142,335 assertions, 8,912 failures. This remains red outside the DOCX/OpenXML slice and was recorded at `/tmp/plib-smisf-full-pandoc.log`.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validator, or live service was invoked.
