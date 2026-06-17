# DOCX OpenXML duplicate part digest rollups

- Bead: plib-mh0ey
- Scope: DOCX/OpenXML package ingestion only.
- Base: origin/main 6140434a5d after rebase.
- Slice: `DocxOpenXmlReader` now summarizes duplicate SHA-256 digest groups across loaded package parts without exposing package bytes.
- Metadata: group/part/byte counts, digest values, CRC32 values, part names, directories, content-type source/base buckets, role buckets, and largest-part provenance.
- Fixture: duplicate XML and binary payload groups cover default, override, missing content-type, and document-relationship-target provenance.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file / 7645 assertions / 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (260 files / 179467 assertions / 0 failures)
