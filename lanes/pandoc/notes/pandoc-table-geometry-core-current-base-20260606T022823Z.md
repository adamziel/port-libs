# Pandoc Table Geometry Markdown Width Approximation

Slice: `pandoc-table-geometry-core-current-base-20260606T022823Z`
Session: `port-dev-pandoc-table-geometry-20260606T022823Z`
Base accepted HEAD: `8939543119a291af01b67d59e9e9d7db95241345`

## Behavior

This slice adds a bounded native table-geometry writer handoff diagnostic for
explicit Pandoc table column widths when targeting the bounded Markdown
pipe-table writer.

`TableGeometry::writerDowngradeDiagnostics()` now reports
`markdown-column-widths-approximated` for Markdown writer aliases when a table
has valid explicit source widths. The diagnostic records:

- normalized and percent widths from the source table;
- valid and missing width columns, including partial-width tables;
- whether the width set is complete, partial, overfull, or underfull;
- the `MarkdownWriter` pipe-table character-padding approximation derived from
  the same bounded width scale.

`TableGeometry::reviewPacket()` carries the same diagnostic in writer downgrade
summaries, so WordPress import review packets can distinguish preserved
WordPress `<colgroup>` widths from Markdown pipe-table padding approximation.
Rendered WordPress table output is unchanged.

## Evidence

Initial focused check after adding the new assertions:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
- Result: `1 test files, 819 assertions, 1 failures`
- Failure: the new partial-width assertion expected a fifth implicit column,
  but `TableGeometry::columnCount()` correctly reports the four effective
  source columns for writer diagnostics.

Focused green runs after implementation and expectation correction:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
- Result: `1 test files, 824 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: `2 test files, 1138 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
- Result: `table geometry handoff self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1156 -> 1157`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1606 -> 1607`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 107`.
- Added `mappedTableGeometryMarkdownWidthApproximationCases: 1`.
- Added `tableGeometryMarkdownWidthApproximationAssertions: 33`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`,
`TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter` paths.

Full upstream Pandoc/Haskell table golden parity remains outside this
micro-slice and still depends on a hydrated Pandoc checkout plus Cabal/Tasty
runner closure. No Pandoc, Cabal solver/build/test command, Haskell runner,
external writer, Word, LibreOffice, zip/unzip, browser renderer, online
sanitizer, online service, or live provider test was executed.

## Non-Overlap And Follow-Up

This does not repeat accepted visual span layout, colspec preservation,
row-head output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-coordinate metadata,
source attributes, `rowspan=0`, colgroup provenance, caption metadata,
RST row-span requirements, AsciiDoc nested-table/block-cell requirements,
Markdown/AsciiDoc footer diagnostics, or LaTeX longtable/footer diagnostics.
This slice owns only Markdown width-approximation review metadata for explicit
source table widths.

Follow-up should keep target-specific rendering of explicit column widths,
richer Markdown width-preservation alternatives, full HTML5 table algorithm
edge cases, and full upstream Pandoc Haskell runner parity as separate bounded
slices.

Root harness: not run - isolated micro-slice.
