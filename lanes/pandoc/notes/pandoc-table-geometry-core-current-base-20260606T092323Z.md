# Pandoc Table Geometry Row Occupancy Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T092323Z`

Base: `9ed5ffe130dc1877de2770c8380f6b7781f3bb67`

## Behavior

`TableGeometry::reviewPacket()` now exposes row occupancy metadata for visual
table geometry audits:

- section-level `rowSlotCounts`, `rowVisualWidths`, `rowSummaries`
- section-level complete/incomplete row counts
- section-level covered/missing row counts and `maxVisualWidth`
- section-level `completeRectangle` and row presence booleans
- packet-level rollups for complete/incomplete rows, covered/missing rows,
  `maxVisualWidth`, and `completeRectangle`

This lets WordPress import review packets distinguish complete visual tables
from tables that carry missing visual slots after colspans, rowspans, or
declared-column expansion without replaying every slot client-side.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1258 assertions, 0 failures`
- Final focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1289 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`TableGeometry`, `AstNode`, `MarkdownReader` table handoff,
`WordPressBlockWriter`, the focused PHP test harness, and the WordPress table
geometry example. The local upstream cache did not contain a Pandoc checkout
for direct source reads in this worktree, so the accepted static upstream
manifest remains the source-truth boundary for this bounded native support
slice.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external table writer, browser renderer, online sanitizer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not rework prior table span layout, section-boundary rowspans,
declared-column overflow, source coordinates, source attributes, body-local
head rows, row-head columns, accessibility header associations, header
abbreviations, caption metadata, nested-table rollups, block-cell content,
colgroup provenance, vertical alignment, or writer requirement diagnostics.
It is a bounded additive review-packet summary over the existing visual grid.

## Follow-Up

Keep full upstream table reader/writer parity, richer DOCX/ODT table metadata,
table normalization beyond review-packet summaries, and Haskell runner
dependency closure as separate bounded slices.
