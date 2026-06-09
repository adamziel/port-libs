# Pandoc Table Geometry Core Current Base - CSS Border Presentation

## Scope

- Added bounded HTML table CSS border presentation provenance to `TableGeometry::reviewPacket()`.
- Normalizes safe source `border-color`, `border-style`, and `border-width` declarations into `tableBorderPresentation` metadata and packet summary fields.
- Adds Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics for lossy border presentation handoff because non-HTML writers need raw HTML or reviewer handling.
- WordPress table output preserves sanitized `border-color`, `border-style`, and `border-width` declarations while dropping unsafe sibling styles such as `border-image`.

## Source Truth And Non-Overlap

- Source truth is the accepted lane-local HTML table reader and WordPress table style sanitizer contract for bounded table CSS style metadata.
- This avoids accepted table geometry clusters for visual spans, section grids, row/global coordinates, source summaries, header associations, width/height, table-layout, border-collapse, placement alignment, frame/rules/border attributes, cellpadding/cellspacing, directionality, captions, footer sections, block cells, nested tables, empty tables, decimal alignment, table background, and cell nowrap.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before starting.
- Baseline focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 815 assertions, 0 failures`
- Red-first after adding the focused border presentation test:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 818 assertions, 1 failures`
  - Failure: source HTML table CSS border presentation did not populate `tableBorderPresentation` metadata.
- Final focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 833 assertions, 0 failures`
- Final focused table-geometry family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2512 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/TableGeometry.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: all reported no syntax errors.
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

## Status Delta

- `lane-status.json` `phpPass`: `2027` -> `2028`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2442` -> `2443`.
- `mappedTableGeometryCoreCases`: `9` -> `10`.
- `tableGeometryCoreAssertions`: `155` -> `173`.
- New focused reader-handoff assertion delta: `+18`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP Markdown HTML table reader, `TableGeometry` review-packet/downgrade metadata, and `WordPressBlockWriter` table style sanitizer. Full upstream Pandoc runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan.

## Follow-Up

A non-overlapping table-geometry follow-up could cover per-cell border presentation provenance, row/column border style inheritance, or importer raw-HTML fallback diagnostics while avoiding accepted table layout, spacing, background, border-collapse, placement, directionality, nowrap, and span geometry clusters.
