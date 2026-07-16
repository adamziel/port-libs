# DOCX OpenXML Bibliography Package Parts

Date: 2026-06-17
Issue: plib-1420t

Scope:
- Native PHP DOCX/OpenXML package ingestion only.
- Adds metadata-only provenance for bibliography package parts reached through `officeDocument/2006/relationships/bibliography`.
- Discovers orphan parts declared with `application/vnd.openxmlformats-officedocument.wordprocessingml.bibliography+xml`.

Recovery surface:
- `DocxOpenXmlReader` now reports `docx.bibliographyParts` and `packageProvenance.bibliographyParts`.
- Package summary counters cover relationship, orphan, existing, missing, external, source, and issue totals.
- Each bibliography item carries target suffixes, content-type provenance, byte length, CRC32, SHA-256, XML root validity, bounded source tag/type/title/author metadata, and metadata-only byte exposure policy.
- Raw bibliography XML is not exposed as document media.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed 1 file / 4056 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 258 files / 175790 assertions / 0 failures after rebase onto `21c3d5e628`.
