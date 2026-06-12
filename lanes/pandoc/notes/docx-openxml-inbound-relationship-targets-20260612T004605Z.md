# DOCX OpenXML Inbound Relationship Targets - 2026-06-12

Slice: `plib-w1ga5`

Implemented package-inventory provenance for inbound DOCX/OpenXML relationship
targets. Each package part now reports record-level relationship references that
target it, including source `.rels` part, source package part, relationship IDs,
target suffix/query/fragment metadata, content type metadata, record ordinal, and
duplicate-ID flags.

The package summary now includes aggregate targeted-part counts, record counts,
multi-targeted part counts, and compact targeted-part rollups for review queues.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 1367 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 68018 assertions, 0 failures
