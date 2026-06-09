# Pandoc Table Geometry Cell Background Handoff - 2026-06-09

Slice: `pandoc-table-geometry-core-current-base-20260609T012742Z`  
Base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Behavior

- Added bounded HTML table cell background metadata to `TableGeometry` review
  packets.
- `th`/`td` `bgcolor` and CSS `background-color` now normalize through the
  existing table-background color parser and expose per-cell source,
  normalized attributes, columns, sections, text, and source attributes.
- Packet summaries now report `hasCellBackgrounds`, background cell counts,
  columns, sections, colors, and source kinds.
- Markdown, AsciiDoc, and LaTeX downgrade diagnostics now report
  cell-background review requirements while WordPress table output preserves
  the source cell attributes.

## Source Truth And Non-Overlap

- Source truth came from the accepted static Pandoc inventory and the existing
  lane-local HTML reader / WordPress table handoff fixtures. The local upstream
  cache did not contain a Pandoc checkout for additional fixture reads.
- This slice deliberately avoided accepted table geometry surfaces for table
  background, cell nowrap, colgroup/char alignment, header `axis`/`abbr`,
  visible/hidden table source attributes, table-foot writer diagnostics,
  block-cell diagnostics, global row coordinates, and cell-width coverage.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer,
  browser renderer, Word, LibreOffice, zip/unzip, online service, live provider
  test, or live-service provider test was executed.

## Focused Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 815 assertions, 0 failures
```

Final focused runs:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 855 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 2550 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: `2037 -> 2038`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2451 -> 2452`.
- Focused reader handoff assertions: `815 -> 855` (`+40`).
- Focused table-family assertions: `2550` final assertions across two test
  files.

## Dependency Closure

No new support component is needed. This reuses the existing native
`MarkdownReader` HTML table path, `TableGeometry` background normalization
helpers, `WordPressBlockWriter`, and the lane-local WordPress table geometry
handoff example. Remaining table geometry work should stay in bounded native
reader/writer metadata handoffs, not external Pandoc or writer execution.
