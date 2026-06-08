# Pandoc Table Geometry Core Current Base - CSS Border Collapse

## Scope

- Added bounded HTML table CSS `border-collapse` provenance to `TableGeometry::reviewPacket()` as table-level review metadata.
- Normalizes safe source `style="border-collapse: collapse"` and `style="border-collapse: separate"` declarations into `tableBorderCollapse.attributes["border-collapse"]`, `borderCollapse`, `borderCollapseSource`, and packet summary fields.
- Adds Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics for lossy border-collapse handoff because non-HTML writers need raw HTML or reviewer handling.
- WordPress table output preserves sanitized `border-collapse:collapse` / `border-collapse:separate` style declarations while dropping unsafe sibling style declarations and invalid values.

## Source Truth And Non-Overlap

- Source truth is the accepted lane-local HTML table reader and WordPress table style sanitizer contract for bounded table CSS style metadata.
- This avoids accepted table geometry clusters for visual spans, section grids, row/global coordinates, source summaries, header associations, width/height, table-layout, placement alignment, frame/rules/border, cellpadding/cellspacing, directionality, captions, footer sections, block cells, nested tables, empty tables, decimal alignment, table background, and cell nowrap.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before starting.
- Red-first after adding the focused border-collapse test:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 791 assertions, 1 failures`
  - Failure: source HTML `border-collapse` style did not populate `tableBorderCollapse` metadata.
- Final focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 815 assertions, 0 failures`
- Final focused table-geometry family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `2 test files, 2494 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

## Status Delta

- `lane-status.json` `phpPass`: `1978` -> `1979`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2397` -> `2398`.
- `mappedTableGeometryCoreCases`: `9` -> `10`.
- `tableGeometryCoreAssertions`: `155` -> `184`.
- New focused reader-handoff assertion delta: `+29`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP Markdown HTML table reader, `TableGeometry` review-packet/downgrade metadata, and `WordPressBlockWriter` table style sanitizer. Full upstream Pandoc runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan.

## Follow-Up

A non-overlapping table-geometry follow-up could cover per-cell/table border style or color provenance for writer handoff, avoiding accepted frame/rules/spacing/background/width/height/table-layout/directionality/nowrap and this border-collapse slice.
