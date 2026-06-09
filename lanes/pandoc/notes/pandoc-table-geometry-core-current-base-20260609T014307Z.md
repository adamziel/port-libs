# Pandoc Table Geometry Cell Border Presentation Slice

Slice: `pandoc-table-geometry-core-current-base-20260609T014307Z`
Base accepted HEAD: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`

## Scope

- Added native HTML `th`/`td` cell border presentation handoff metadata for bounded `border-color`, `border-style`, and `border-width` declarations.
- Reused existing table border CSS normalization so the cell packet records only safe normalized color/style/width values and source attributes.
- Added Markdown, AsciiDoc, and LaTeX downgrade diagnostics for per-cell border presentation because those writers need raw HTML or reviewer-specific handling.
- Extended the WordPress table geometry handoff example with a local self-test that validates packet summaries, downgrade codes, and preserved safe cell styles.

## Evidence

- Baseline focused reader check before edits:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 873 assertions, 0 failures`.
- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 876 assertions, 1 failures`; failure was the missing `cellBorderPresentations` packet count (`Expected: 3`, `Actual: 0`).
- Final focused reader check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 912 assertions, 0 failures`.
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 2648 assertions, 0 failures`.
- Syntax checks:
  `php -l lanes/pandoc/src/TableGeometry.php`,
  `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  -> no syntax errors.
- JSON sanity:
  `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decoded with `JSON_THROW_ON_ERROR`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  -> passed.

## Status Delta

- `lane-status.json` `phpPass`: `2072 -> 2073`.
- `UPSTREAM_TEST_MANIFEST.json` benchmark denominator mapped: `2484 -> 2485`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 194`.

## Dependency Closure

No new support component is needed. This slice reuses native `TableGeometry` review packets, `MarkdownReader` HTML table ingestion, `WordPressBlockWriter` HTML cell style handoff, and the existing bounded CSS border normalization helpers. Pandoc, Cabal/Haskell runners, external writers, Word, LibreOffice, zip/unzip, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This is distinct from the accepted table-level border presentation and cell background handoff slices. It only covers per-cell border presentation metadata and writer downgrade summaries for HTML table cells.

## Follow-Up

Possible next non-overlapping table geometry work: per-side cell border provenance (`border-top-*`, `border-right-*`, etc.), caption-width placement interactions, or additional writer downgrade summaries for complex table body groups.
