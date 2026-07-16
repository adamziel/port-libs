# DOCX/OpenXML Relationship Type Summary

Slice: DOCX OpenXML package ingestion relationship-type provenance on current base `0bd0f6f3e`.

## What changed

- `DocxOpenXmlReader` now derives `packageProvenance.relationshipTypes` from the already parsed root, document, and secondary `.rels` parts.
- Each relationship type bucket records a compact reviewer summary: label, total count, internal/external counts, existing/missing target counts, relationship parts, source parts, target parts, external targets, content types, and per-relationship provenance.
- The behavior is metadata-only. Document parsing, rendering, relationship resolution, and media byte exposure are unchanged.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 402 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 63015 assertions, 0 failures

## Direct-format parity accounting

- `phpPass`: `3058 -> 3059`
- `phpFail`: `0 -> 0`
- Focused DOCX/OpenXML assertions now cover relationship-type summaries across root, document, and secondary relationship parts.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, TeX/PDF engines, external validators, online services, live provider tests, or live-service provider tests were invoked.
