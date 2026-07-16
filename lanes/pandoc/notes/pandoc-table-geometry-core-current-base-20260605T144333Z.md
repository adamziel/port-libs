# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T144333Z`

Base accepted HEAD: `43fe2e469952f027d78a8423066fb37ecd32565d`

## Behavior Added

- Added JSON-safe block-content summaries for table cells that contain direct
  block children such as paragraphs and lists.
- Table geometry review packets now expose block content on coverage records,
  section grid slots, section summaries, and the global summary without
  leaking `AstNode` references.
- `TableGeometry::writerDowngradeDiagnostics()` now reports Markdown
  `markdown-cell-blocks-flattened` and AsciiDoc
  `asciidoc-block-cell-required` handoff requirements for non-nested
  block-content cells.
- WordPress table output continues to render paragraph and list block content
  inside table cells, while the review packet records the lossy writer
  requirement for targets that need multiline or block-cell support.
- The WordPress table-geometry smoke now includes a paragraph-plus-list table
  cell and self-tests review-packet metadata, writer diagnostics, and rendered
  WordPress output.

## Source Truth

- Uses Pandoc's existing AST contract where table cells contain block lists,
  including accepted native fixture coverage from `test/html-reader.html` /
  `test/html-reader.native` for paragraph-bearing table cells.
- Reuses already mapped table geometry rows from `test/tables.native`,
  `test/tables.markdown`, `test/pipe-tables.txt`, and the accepted writer
  downgrade fixtures for Markdown/RST/AsciiDoc table handoff constraints.
- This is bounded native PHP support-library behavior. No Pandoc binary,
  Cabal solver/build/test command, Haskell runner, external Markdown/AsciiDoc
  writer, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer,
  online sanitizer, or online service was executed.

## Verification

- Baseline focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 785 assertions, 0 failures`.
- Red-first focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 549 assertions, 1 failures`.
  - Failing edge: review-packet content metadata did not report
    `hasBlockContent` for a paragraph/list table cell.
- Focused table geometry after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 586 assertions, 0 failures`.
- Focused table family after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 824 assertions, 0 failures`.
- Table plus Markdown reader family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `3 test files, 3688 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`.
- PHP syntax:
  `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case.
- Raised `lanes/pandoc/lane-status.json` `phpPass`: `951 -> 952`.
- Raised `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1406 -> 1407`.
- Raised `TableGeometryTest.php` focused coverage: `547 -> 586` assertions.
- Raised focused table-family coverage: `785 -> 824` assertions.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 113`.
- Added `mappedTableGeometryBlockCellContentCases: 1`.
- Added `tableGeometryBlockCellContentAssertions: 39`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source coordinates, source-coordinate
shifts, occupied slots, accessibility relationships, reader packet attachment,
nested-table rollup summaries, source attributes, `rowspan=0`, Markdown
rowspan downgrades, RST grid-table requirements, AsciiDoc nested-table
raw-HTML requirements, HTML colgroup width/alignment expansion, per-column
colgroup provenance, column-group runs, colgroup mismatch diagnostics,
caption inline/block metadata, overfull-width diagnostics, or malformed span
normalization. This slice owns only block-level paragraph/list table-cell
content metadata and target-writer block-cell handoff diagnostics.

## Dependency Closure

No new native support component is needed. The slice reuses the existing
`AstNode`, `TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter`
support paths.

Full upstream Pandoc runner parity remains gated on hydrating a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` before any non-mutating
Cabal solver/build plan. This slice did not attempt that runner.

## Follow-Up

Keep full target-writer multiline table serialization, richer grid/simple
table writer selection, additional target-specific block-cell downgrade
requirements, and full HTML5 table algorithm parity as separate bounded
slices.
