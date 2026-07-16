# Pandoc DOCX header/footer provenance current-base slice

Date: 2026-06-11 UTC
Bead: plib-2rkvq
Scope: lanes/pandoc only
Base: current origin/main 0c646c473

## Change

- `DocxOpenXmlReader` now scans `w:headerReference` and `w:footerReference` section properties and summarizes document relationship-selected header and footer parts under `docx.headers` and `docx.footers`.
- Each summary preserves relationship id, reference type, target part, content-type provenance, existence, valid root status, part-local relationship count, block count, and plain text.
- Header/footer text uses the existing DOCX paragraph/table ingestion path with part-local relationships, so linked text and image alt text are represented without rendering header/footer content into body blocks.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed 1 test file, 515 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 63856 assertions, 0 failures.
