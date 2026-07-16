# DOCX/OpenXML numbering fallback

Slice: `pandoc-docx-numbering-fallback-current-base-20260609T215051Z`

## Summary

- `DocxReader` now resolves numbering definitions through the document-level
  numbering relationship first, then falls back to the conventional sibling
  `numbering.xml` part beside the office document when no numbering relationship
  exists.
- The fallback imports list definitions into the same AST list path as
  relationship-selected numbering, while leaving `docxNumbering` relationship
  provenance absent so review metadata does not invent a source relationship.
- The focused fixture keeps `word/numbering.xml` present, removes only the
  document numbering relationship, and verifies Markdown plus WordPress list
  output from native PHP package/XML parsing.

## Evidence

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed: 1 selected test file, 4476 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase: 42 selected test files, 58282 assertions, 0 failures.
- Lane accounting moved from `phpPass` 2900 / `suiteProgress` 803 to
  `phpPass` 2901 / `suiteProgress` 804 on the current base.

## Boundary

This slice stays under `lanes/pandoc`, uses existing native PHP OPC/DOCX
package readers, and does not invoke Pandoc, Word, LibreOffice, `zip`, `unzip`,
external validators, online services, live provider tests, or live-service
provider tests. Direct-format parity accounting is not affected; this is a DOCX
reader package-resolution fallback.
