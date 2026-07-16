# DOCX/OpenXML numbering relationship target

Slice: `pandoc-docx-openxml-core-current-base-20260609T193048Z`

## What changed

- `DocxOpenXmlReader` now resolves WordprocessingML numbering definitions from
  the main document relationship whose type is
  `officeDocument/2006/relationships/numbering`.
- The compact DOCX metadata exposes `numberingPart` plus a
  `numberingRelationship` summary with source part, relationships part,
  target, resolved target, target part, existence, and content type.
- If no numbering relationship is present, the reader keeps the previous
  conventional fallback to the document sibling `numbering.xml` part.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 66 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `42 test files, 57064 assertions, 0 failures`

## Accounting

- Adds 1 focused PHP PASS case and 16 assertions.
- `lane-status.json` `phpPass`: `2829 -> 2830`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3044 -> 3045`.

## Scope

This slice is limited to the compact native PHP DOCX OpenXML reader. It does
not alter the richer `DocxReader` path, list rendering, nested numbering,
style-linked numbering, OPC preflight policy, glossary relationships, media,
tables, or tracked changes. No Pandoc, Cabal/Haskell runner, Word,
LibreOffice, `zip`/`unzip`, browser renderer, external validator, online
service, live provider test, or live-service provider test was run.
