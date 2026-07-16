# DOCX/OpenXML ZIP Package Comment Source Provenance - 2026-06-30

## Slice

`plib-ozrk9` carries source ZIP EOCD package-comment byte-range provenance through DOCX/OpenXML package ingestion.

## What changed

- `DocxOpenXmlReader` mirrors existing ZIP package comment source provenance into `packageProvenance.summary` for importer review handoff.
- The DOCX package comment review surface now exposes source availability, offset, byte count, end offset, and SHA-256 hash alongside the decoded comment metadata.
- The existing DOCX ZIP comment fixture verifies the decoded comment preflight remains intact while the source byte-range metadata is carried separately.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 10,543 assertions, 0 failures

This stays inside native PHP DOCX/OpenXML package ingestion and uses existing ZIP package preflight primitives. No Pandoc, office suite, ZIP CLI, browser, TeX, Node, or external validator is invoked.
