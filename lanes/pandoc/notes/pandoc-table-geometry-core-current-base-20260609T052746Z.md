# Pandoc Table Geometry Core Current Base 20260609T052746Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-table-geometry-core-current-base-20260609T052746Z`
- Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`
- Behavior cluster: HTML table column background handoff for `<colgroup>` and `<col>` sources.

## Source Truth

- Pandoc table conversion must preserve source table geometry and presentation metadata that affects downstream writers.
- Existing native HTML table reader support already expands colgroup/col source records for widths, alignment, vertical alignment, span provenance, and decimal alignment.
- This slice extends that same bounded source-record path to background color presentation:
  - `TableGeometry::reviewPacket()` now emits `columnBackgrounds`.
  - Review packet summaries expose column background count, affected visual columns, colors, source type, and source element.
  - Markdown, AsciiDoc, and LaTeX downgrade diagnostics report that column backgrounds require raw HTML or writer-specific review.
  - `WordPressBlockWriter` carries sanitized `background-color` styles onto generated `<col>` elements and strips unsafe source CSS such as `background-image:url(javascript:...)`.

## Focused Evidence

Red-first check after adding the new focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes html column background metadata for geometry and wordpress handoff
1 test files, 1165 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes html column background metadata for geometry and wordpress handoff
1 test files, 1199 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Delta from the prior accepted table-geometry reader handoff: +1 focused PHP PASS case and +36 focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP HTML-reader colgroup provenance, existing table background normalization helpers, existing writer downgrade machinery, and WordPress table attribute/style sanitization. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, TeX/PDF engine, Typst, browser renderer, zip/unzip, external converter, online service, live provider test, or live-service provider test was run.

## Non-overlap

This intentionally avoids the already accepted table section presentation, row/cell background and border presentation, table background/layout/frame/spacing/directionality, caption-source, scope/axis/abbr/header association, row-head, rowspan-zero, colgroup width/alignment/provenance, and decimal-alignment clusters. Follow-up table geometry work should target a distinct gap such as column border presentation, invalid column style diagnostics, or further WordPress-safe inherited column presentation rules.
