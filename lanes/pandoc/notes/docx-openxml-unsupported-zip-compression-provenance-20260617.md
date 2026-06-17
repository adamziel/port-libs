# DOCX OpenXML Unsupported ZIP Compression Provenance

Bead: `plib-616hg`

Slice: `pandoc-docx-openxml-unsupported-zip-compression-provenance`

Scope:
- Keeps the work in the DOCX/OpenXML package ingestion lane.
- Handles noncritical ZIP entries with unsupported compression methods as metadata-only package members instead of aborting `readZipPackage()`.
- Preserves blocked member names, byte buckets, unsupported entry snapshots, and `zip-unsupported-compression` roles in DOCX package provenance.

Accounting:
- Adds `mappedDocxOpenXmlUnsupportedZipCompressionCases = 1`.
- Adds `docxOpenXmlUnsupportedZipCompressionAssertions = 37`.
- Moves `phpPass` from 17053 to 17054; `phpFail` remains 0.
- Moves upstream mapped cases from 16639 to 16640, root mapped inventory from 16608 to 16609, and benchmark mapped cases from 3777 to 3778.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 5170 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` -> 258 files, 176938 assertions, 0 failures.
