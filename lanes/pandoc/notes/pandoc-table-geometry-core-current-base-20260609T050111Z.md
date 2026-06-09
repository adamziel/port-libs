# Pandoc Table Geometry Section Presentation Handoff

Slice: `pandoc-table-geometry-core-current-base-20260609T050111Z`  
Base accepted HEAD: `945c3c6f54718c2e2a84ea6013a7f69ab7cd1d9a`

## Behavior

- Added bounded native PHP table-section presentation metadata for HTML
  `thead`, `tbody`, and `tfoot` source nodes.
- `TableGeometry::reviewPacket()` now exposes `sectionBackgrounds` and
  `sectionBorderPresentations` records with section names, local/global row
  ranges, column counts, normalized background colors, aggregate border
  properties, side-border edges, and safe source attributes.
- Review-packet summaries now roll up section presentation counts, sections,
  colors, sources, edge names, edge colors, edge styles, and edge widths.
- Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics now report
  section-background and section-border-presentation requirements while the
  WordPress handoff keeps safe source section attributes in the rendered table.

## Source Truth And Non-Overlap

- Source truth is the accepted Pandoc static table inventory plus the existing
  lane-local HTML-reader and WordPress table handoff behavior for structured
  table sections.
- This slice deliberately avoids accepted table geometry clusters for table,
  row, and cell background/border presentation; row-head columns; section
  scoped rowspans; source headers/scope/axis/abbr; colgroup provenance;
  directionality; table layout/frame/spacing; and DOCX/ODF package handoffs.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external converter, browser renderer, online service,
  live provider test, or live-service provider test was executed.

## Focused Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1109 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1163 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: `2343 -> 2344`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2738 -> 2739`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 209`.
- Focused reader assertions: `1109 -> 1163` (`+54`).

## Dependency Closure

No new support component is needed. This reuses native PHP `MarkdownReader`
HTML table parsing, `TableGeometry` section grids/source attributes, existing
bounded color and border normalizers, `WordPressBlockWriter`, and the
lane-local PHP test runner. Full upstream Pandoc runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned upstream
sources and Haskell test executables.

Root harness: not run - isolated micro-slice.
