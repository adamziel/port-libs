# Pandoc Table Geometry Current Base - LaTeX Writer Requirements

Slice: `pandoc-table-geometry-core-current-base-20260605T165349Z`

Accepted base: `017e60c2d3368584565a1ace8949235ce293214b`

## Source Truth

Pandoc table ASTs can carry row spans, column spans, block-level cell content,
and nested tables that need target-specific writer support. The native PHP
handoff should not imply that these shapes are safe for LaTeX output until a
real LaTeX writer handles `multicolumn`, `multirow`, parbox/minipage-style
cell wrappers, and nested tabular/minipage handoffs.

## Implementation

- `TableGeometry::writerDowngradeDiagnosticsFromCoverage()` now recognizes the
  normalized LaTeX writer family (`latex`, `tex`, `pdflatex`, `xelatex`,
  `lualatex`, and `latexmk`).
- LaTeX review diagnostics now report required `multicolumn` support for cells
  with `colspan > 1`, `multirow` support for cells with `rowspan > 1`,
  `parbox-or-minipage-cell` support for block-content cells, and
  `nested-tabular-minipage` support for nested table cells.
- The diagnostics include flattened visual slots, source row/section metadata,
  required features, block-content summaries, and nested-table summaries so
  WordPress import queues can route reviewer follow-up without invoking a TeX
  engine.
- `wordpress-table-geometry-handoff.php --self-test` now includes a LaTeX
  requirement review table and verifies the requirement packet.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 868 assertions, 0 failures`
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 631 assertions, 1 failures`
  - Failed because LaTeX writer diagnostics were missing.
- Green: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 664 assertions, 0 failures`
- Final focused table geometry run: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 902 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`: passed

## Status Delta

- `lane-status.json` `phpPass`: `1005` to `1006`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1460` to `1461`
- `mappedTableGeometryCoreCases`: `6` to `7`
- `tableGeometryCoreAssertions`: `74` to `108`
- New native case markers:
  - `mappedTableGeometryLatexWriterRequirementCases`: `1`
  - `tableGeometryLatexWriterRequirementAssertions`: `34`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`AstNode`, `TableGeometry`, `WordPressBlockWriter`, and table review-packet
support paths. No Pandoc, Cabal solver/build/test command, Haskell runner,
TeX/PDF engine, external writer, Word, LibreOffice, office tool, browser
renderer, online sanitizer, or online service was executed.

## Non-Overlap And Follow-Up

This slice does not overlap the recent table geometry section-boundary,
declared-column overflow, nested-table, accessibility-header, RST/AsciiDoc
handoff, underfull-width, or block-cell content slices. It covers only bounded
LaTeX writer requirement diagnostics for existing table span/block/nested
geometry.

Future table work should keep full LaTeX tabular/longtable rendering,
automatic LaTeX package insertion, PDF engine integration, CSS table
layout/cascade, richer DOCX/ODT table style rendering, and full upstream Pandoc
Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
