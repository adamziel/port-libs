# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T140812Z`

Base accepted HEAD: `fb29a2c779889fe1a2b73403a047d332f896248e`

## Behavior Added

- Added AsciiDoc writer alias normalization for `asciidoc`, `asciidoctor`,
  `asciidoc-legacy`, and `adoc` in `TableGeometry` writer diagnostics.
- `TableGeometry::writerDowngradeDiagnostics()` now emits
  `asciidoc-nested-table-raw-html-required` when a table cell contains nested
  table descendants.
- The diagnostic records section/row/source coordinates, visual columns,
  `reason: nested-table`, `requiredFeature: raw-html-table-passthrough`,
  nested-table count, captions, diagnostic codes, and JSON-safe nested table
  summaries without leaking `AstNode` references.
- `TableGeometry::reviewPacket()` can now include the AsciiDoc requirement via
  `writers: ["markdown", "asciidoc"]`, while default review packets and
  rendered WordPress/Markdown table output remain unchanged.
- The WordPress table-geometry handoff smoke now verifies the AsciiDoc
  nested-table writer requirement alongside the existing nested-table review
  packet rollup.

## Source Truth

- Uses the accepted pinned Pandoc static inventory row for
  `test/command/nested-table-to-asciidoc-6942.md`, already recorded in
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` as the nested-table AsciiDoc
  command fixture.
- Reuses the already mapped table rows from `test/html-reader.html`,
  `test/html-reader.native`, `test/tables.native`, `test/tables.markdown`, and
  `test/pipe-tables.txt` for table geometry, spans, sections, and nested table
  handoff shape.
- This is bounded native PHP support-library behavior. No Pandoc binary,
  Cabal solver/build/test command, Haskell runner, external AsciiDoc writer,
  Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online
  sanitizer, or online service was executed.

## Verification

- Baseline focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 525 assertions, 0 failures`.
- Baseline focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 763 assertions, 0 failures`.
- Focused table geometry after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 547 assertions, 0 failures`.
- Focused table family after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 785 assertions, 0 failures`.
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
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case.
- Raised `lanes/pandoc/lane-status.json` `phpPass`: `939 -> 940`.
- Raised `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1395 -> 1396`.
- Raised `TableGeometryTest.php` focused coverage: `525 -> 547` assertions.
- Raised focused table-family coverage: `763 -> 785` assertions.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 96`.
- Added `mappedTableGeometryAsciidocNestedRequirementCases: 1`.
- Added `tableGeometryAsciidocNestedRequirementAssertions: 22`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source coordinates, source-coordinate
shifts, occupied slots, accessibility relationships, reader packet attachment,
nested-table rollup summaries, source attributes, `rowspan=0`, Markdown span
downgrades, RST grid-table requirements, HTML colgroup width/alignment
expansion, per-column colgroup provenance, column-group runs, colgroup mismatch
diagnostics, caption inline/block metadata, overfull-width diagnostics, or
malformed span normalization. This slice owns only the AsciiDoc nested-table
writer requirement handoff layered on top of the existing nested-table
geometry summaries.

## Dependency Closure

No new native support component is needed. The slice reuses the existing
`AstNode`, `TableGeometry`, and `WordPressBlockWriter` support paths.

Full upstream Pandoc runner parity remains gated on hydrating a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` before any non-mutating
Cabal solver/build plan. This slice did not attempt that runner.

## Follow-Up

Keep default accessibility emission policy, caption/short-caption writer edge
cases, col-width formatting, full HTML5 table algorithm parity, and additional
target-specific writer downgrades as separate bounded slices.
