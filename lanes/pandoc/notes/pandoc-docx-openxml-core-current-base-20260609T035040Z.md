# DOCX/OpenXML chart text metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T035040Z`
Base accepted HEAD: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Behavior

- `DocxReader` now reads bounded chart-part text metadata from DOCX chart XML.
- Chart placeholders preserve chart title text as `docx-chart-title` and `data-docx-chart-title`.
- Chart series labels are preserved as `docx-chart-series`, `data-docx-chart-series-count`, and bounded per-series `index`, `order`, and `name` attributes.
- Chart axis titles are preserved as `docx-chart-axis-title`, `data-docx-chart-axis-title-count`, and bounded per-axis type/id/title attributes.
- The placeholder remains metadata-only. The reader does not render charts, parse workbook bytes, run spreadsheet tooling, or invoke office converters.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed before this slice.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed with 2 failures because chart placeholders lacked `docx-chart-title`, `docx-chart-series`, and `docx-chart-axis-title` metadata.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3818 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-chart-data-handoff.php --self-test`
  passed with `wordpress-docx-chart-data-handoff self-test passed`.

## Delta

- Added one focused DOCX/OpenXML PHP PASS case.
- Added 41 focused DOCX assertions.
- `phpPass`: `2254 -> 2255`.
- `benchmarkDenominator.mapped`: `2660 -> 2661`.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 426`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `DocxReader`
DOM parsing, `ZipPackage`, OPC relationship primitives, `MarkdownWriter`,
`WordPressBlockWriter`, and the focused lane TestRunner.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, spreadsheet reader, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted chart embedded-workbook provenance, chart
style/color relationship handoff, chart/diagram placeholders, DrawingML
geometry, connector/group-shape metadata, picture metadata/effects, image
hyperlinks, content controls, custom XML binding, settings, comments/endnotes,
table metadata, tracked revisions, embedded OLE/package placeholders,
subdocuments, or OPC relationship preflight work. It only closes chart title,
series label, and axis title review metadata.

## Next

A next DOCX/OpenXML slice could cover chart data table metadata, theme color
inheritance edges, or table/numbering style interaction without repeating chart
text metadata.
