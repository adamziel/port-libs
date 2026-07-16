# Pandoc Table Geometry Core Current Base - CSS Table Layout

## Scope

- Added bounded HTML table CSS `table-layout` provenance to `TableGeometry::reviewPacket()` as table-level layout metadata.
- Normalizes safe source `style="table-layout: fixed"` and `style="table-layout: auto"` declarations into `tableLayout.attributes["table-layout"]`, `layoutMode`, `layoutModeSource`, and packet summary fields.
- Adds Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics for lossy table-layout-mode handoff because non-HTML writers need raw HTML or reviewer handling.
- WordPress table output preserves sanitized `table-layout:fixed` / `table-layout:auto` style declarations while dropping unsafe sibling style declarations.

## Source Truth And Non-Overlap

- Source truth is the accepted lane-local HTML table reader and WordPress table style sanitizer contract for bounded table layout metadata.
- This avoids accepted table geometry clusters for visual spans, section grids, row/global coordinates, source summaries, header associations, width/height, placement alignment, frame/rules/border, cellpadding/cellspacing, directionality, captions, footer sections, block cells, nested tables, empty tables, decimal alignment, table background, and cell nowrap.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before starting.
- Red-first after adding the focused table-layout test:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 763 assertions, 1 failures`
  - Failure: source HTML `table-layout` style did not populate `tableLayout` metadata.
- Final focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 786 assertions, 0 failures`
- Final focused table-geometry family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2465 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/TableGeometry.php`
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.

## Status Delta

- `lane-status.json` `phpPass`: `1964` -> `1965`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2385` -> `2386`.
- `mappedTableGeometryCoreCases`: `9` -> `10`.
- `tableGeometryCoreAssertions`: `155` -> `183`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP Markdown HTML table reader, `TableGeometry` review-packet/downgrade metadata, and `WordPressBlockWriter` table style sanitizer. Full upstream Pandoc runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan.

## Follow-Up

A non-overlapping table-geometry follow-up could cover caption-side/layout provenance, table `border-collapse` CSS handoff, or importer-side raw-HTML fallback diagnostics not already covered by width/height, table-layout, alignment, frame/rules, spacing, directionality, background, and nowrap metadata.
