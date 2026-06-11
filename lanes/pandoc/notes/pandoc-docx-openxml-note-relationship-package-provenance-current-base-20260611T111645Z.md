# DOCX OpenXML Note Relationship Package Provenance

Scope: native PHP DOCX/OpenXML package ingestion on required base
`407d7449945672e0605a25fb4a4b5888a14c2249`.

## What Changed

- `DocxOpenXmlReader` now carries consumed footnote and endnote relationship
  parts into `docx.packageProvenance.relationshipParts`.
- Package part inventory now gives those note-owned `.rels` parts explicit
  roles and marks their internal targets as footnote/endnote relationship
  targets.
- Relationship summaries preserve source part, bytes, count, external target
  classification, internal target existence, content-type provenance, and
  query/fragment suffix metadata.

## Focused Coverage

- Extended `DocxOpenXmlReaderTest.php` relationship-selected footnote/endnote
  coverage with 24 package provenance assertions.
- Focused result: 1 test file, 341 assertions, 0 failures.
- Full lane result: 44 test files, 62542 assertions, 0 failures.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was run.
