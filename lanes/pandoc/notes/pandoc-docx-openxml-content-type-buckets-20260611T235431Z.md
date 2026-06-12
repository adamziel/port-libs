# DOCX OpenXML content-type bucket package provenance

Hook: `plib-lh4i0`, Pandoc DOCX OpenXML package ingestion core blocker slice
`20260611T235431Z`.

## Implementation

- Added `DocxOpenXmlReader` package summary buckets grouped by resolved
  content-type base.
- Each bucket now exposes exact content-type declarations, part counts, byte
  totals, relationship-part counts, parameterized declaration counts, content
  type source counts, default extensions, override part names, role counts, and
  ordered part names.
- Missing content-type coverage is grouped into an inert `(missing)` review
  bucket without exposing additional document media bytes.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1328 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67781 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, zip/unzip,
browser renderer, external validator, online service, live provider test, or
live-service provider test was executed.
