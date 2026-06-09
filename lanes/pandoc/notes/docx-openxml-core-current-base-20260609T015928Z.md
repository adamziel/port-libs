# DOCX/OpenXML chart embedded-data provenance

Slice: `pandoc-docx-openxml-core-current-base-20260609T015928Z`
Base: `afefe2709cd2d600e733f14d1a2c7daf937774dc`

## Behavior

- `DocxReader` chart placeholders now inspect the resolved chart part for `c:externalData`.
- When the chart part has its own OPC relationships part, the reader resolves the external-data relationship and preserves metadata-only reviewer attributes for the chart-local `.rels` part, relationship count, externalData id, `c:autoUpdate`, embedded package target, target part, external/internal state, content type, existence, and byte count.
- The embedded workbook/package bytes remain opaque. The reader does not parse workbook contents, render chart data, or invoke Pandoc, Word, LibreOffice, zip/unzip, or spreadsheet tooling.
- Missing, invalid, or unresolved chart data relationships are tolerated as chart placeholder metadata issues instead of making DOCX body import fail.
- Markdown and WordPress block writers serialize the new attributes through the existing span metadata path.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3496 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3511 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-chart-data-handoff.php --self-test`
  passed with `wordpress-docx-chart-data-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, OPC content-type and relationship primitives, DOM-based chart XML parsing in `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane TestRunner.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, spreadsheet reader, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted custom XML store diagnostics, content-control binding metadata, picture nonvisual metadata, picture effects, image hyperlinks, drawing geometry, diagram placeholders, embedded OLE/package placeholders, subdocument placeholders, settings, comments/endnotes, table metadata, numbering, style inheritance, OMML, or OPC graph target preflight work. It only closes chart-local embedded-data relationship provenance for existing chart placeholders.

## Next

A next DOCX/OpenXML slice could cover connector/group-shape metadata, theme font inheritance edges, chart style/color metadata, or richer table/numbering import without repeating chart embedded-data provenance.
