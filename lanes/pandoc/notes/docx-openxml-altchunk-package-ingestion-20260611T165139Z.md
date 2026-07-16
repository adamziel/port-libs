# DOCX OpenXML altChunk package ingestion

Bead: plib-d9kf6
Date: 2026-06-11 UTC
Base: current main 96af5e2be

## Scope

DocxOpenXmlReader now exposes metadata-only `docx.alternativeFormats` summaries for document-level `w:altChunk` entries and document `aFChunk` relationships. The summary preserves referenced and unreferenced relationship ids, target parts, query/fragment suffixes, content-type provenance, byte counts, part-local relationship counts, and external/missing/unknown diagnostics.

This keeps embedded alternative-format payload parsing out of the compact OpenXML package reader while making the DOCX package boundary auditable for review queues.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 596 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64047 assertions, 0 failures
