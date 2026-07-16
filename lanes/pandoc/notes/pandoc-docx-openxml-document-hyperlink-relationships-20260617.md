# DOCX OpenXML document hyperlink relationships

Slice: `pandoc-docx-openxml-document-hyperlink-relationships`

Bead: `plib-d4muw`

Base: `origin/main` at `8d3f2c3d4f`

## Scope

This recovery slice adds metadata-only package provenance for `w:hyperlink` declarations in the main DOCX document part. `DocxOpenXmlReader` now summarizes main-document hyperlink relationships from `word/_rels/document.xml.rels` alongside anchor-only links, relationship+anchor links, unreferenced hyperlink relationships, unknown relationship IDs, unsafe external schemes, internal package target suffix/query/fragment details, content-type metadata, byte length, CRC32, and SHA-256.

The slice stays under `lanes/pandoc`, does not fetch hyperlink targets, and does not expose hyperlink target parts as document media.

## Accounting

- `phpPass`: `17068 -> 17069`
- `phpFail`: `0`
- `mappedDocxOpenXmlDocumentHyperlinkRelationshipCases`: `1`
- `docxOpenXmlDocumentHyperlinkRelationshipAssertions`: `102`
- Upstream manifest mapped cases: `16654 -> 16655`
- Root mapped inventory: `16623 -> 16624`
- Benchmark denominator mapped cases: `3792 -> 3793`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file / 5969 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 files / 177737 assertions / 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- conflict-marker scan
- `git diff --check`

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests are invoked.
