# DOCX/OpenXML chart style and color metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T033322Z`
Base: `5a15a7a63f3c59d035e33a0be022ea134979a702`

## Behavior

- `DocxReader` now preserves bounded chart-part `c:style/@val` metadata on DOCX chart placeholders.
- `DocxReader` now preserves chart `c:clrMapOvr` color-map override metadata, including background/text/accent/hyperlink color slots.
- Chart-local `chartStyle` and `chartColorStyle` relationships are resolved through the chart part relationship file and exposed as inert reviewer metadata, including relationship ids, target parts, content types, existence flags, and byte counts.
- The chart placeholder remains metadata-only. The importer does not render chart geometry, parse embedded workbook bytes, run spreadsheet tooling, or invoke office converters.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed before this slice.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3727 assertions, 0 failures`.
- Red-first focused test after implementation exposed new metadata but before updating stale expectations:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed because the old chart placeholder expected only the embedded-data class and the old reachable relationship count.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3777 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-chart-data-handoff.php --self-test`
  passed with `wordpress-docx-chart-data-handoff self-test passed`.

## Delta

- Added one focused DOCX/OpenXML PHP PASS case.
- Added 50 focused DOCX assertions.
- `phpPass`: `2235 -> 2236`.
- `benchmarkDenominator.mapped`: `2644 -> 2645`.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 435`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, OPC relationship parsing, DOM-based DOCX chart-part parsing in `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, the focused lane TestRunner, and the existing WordPress DOCX chart-data example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, spreadsheet tool, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted chart embedded-workbook provenance, chart/diagram placeholder handoff, DrawingML geometry, connector/group-shape metadata, picture nonvisual metadata, picture effects, image hyperlinks, content controls, custom XML binding, settings, comments/endnotes, table metadata, tracked revisions, embedded OLE/package placeholders, subdocuments, or OPC relationship preflight work. It only closes chart style/color mapping and chart-local style relationship metadata.

## Next

A next DOCX/OpenXML slice could cover chart axis/title metadata, theme color inheritance edges, table/numbering style interaction, or another chart metadata path that does not repeat chart style/color relationship handoff.
