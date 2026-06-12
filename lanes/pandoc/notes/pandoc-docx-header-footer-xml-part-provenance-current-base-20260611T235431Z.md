# DOCX header/footer XML part provenance

Bead: `plib-lh4i0`

Current base: `e87e631746`

## Scope

`DocxOpenXmlReader` now records package-level provenance for document
relationship-selected header and footer XML parts in
`packageProvenance.headerFooterXmlParts`.

The summary keeps repeated header/footer rows separate from
`selectedXmlParts`, which is keyed by one item per selected XML kind. Each row
captures source type, relationship id, package part name, section reference
types, expected root and content type, actual root state, content type
resolution, target suffix/query/fragment, part-local relationship counts, text
rollups, and issue codes.

This slice covers missing header/footer relationship targets, unexpected root
elements, and unexpected or missing content types as inert package-review
metadata. It does not invoke Pandoc, Word, LibreOffice, office suites,
zip/unzip, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 1239 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 67435 assertions, 0 failures`

## Status Delta

- `phpPass`: `3146 -> 3147`
- Added one focused DOCX package-ingestion PASS case.
- Added 68 focused DOCX assertions.
- Added `mappedDocxOpenXmlHeaderFooterXmlPartCases = 1`.
- Added `docxOpenXmlHeaderFooterXmlPartAssertions = 68`.
