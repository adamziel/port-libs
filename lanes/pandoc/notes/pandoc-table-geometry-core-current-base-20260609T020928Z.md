# Pandoc Table Geometry Cell Side-Border Slice

Slice: `pandoc-table-geometry-core-current-base-20260609T020928Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Scope

- Added native HTML table-cell side-border provenance for safe `border-top`, `border-right`, `border-bottom`, and `border-left` CSS declarations.
- Normalized side-border shorthand into edge metadata with width/style/color fields, and normalized longhand `border-*-width`, `border-*-style`, and `border-*-color` declarations into the same packet shape.
- Surfaced edge counts, edge names, widths, styles, and colors in table geometry summaries and Markdown/AsciiDoc/LaTeX downgrade diagnostics.
- Extended the WordPress table geometry handoff smoke so raw safe side-border styles remain visible while review packets expose structured edge provenance.

## Evidence

- Baseline focused reader check before edits:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 912 assertions, 0 failures`.
- Red-first after adding the focused side-border test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 915 assertions, 1 failures`; failure was the missing `cellBorderPresentations` packet count (`Expected: 3`, `Actual: 0`).
- Final focused reader check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 945 assertions, 0 failures`.

## Status Delta

- `lane-status.json` `phpPass`: `2124 -> 2125`.
- `UPSTREAM_TEST_MANIFEST.json` benchmark denominator mapped: `2551 -> 2552`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 188`.
- Focused reader assertion growth: `912 -> 945` (`+33`).

## Dependency Closure

No new support component is needed. This slice reuses native `MarkdownReader` HTML table ingestion, `TableGeometry` review packets, existing bounded CSS color/style/width normalizers, and `WordPressBlockWriter` raw safe cell-style handoff. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This follows the accepted table-level border, aggregate cell border presentation, and cell background slices without changing their behavior. The new behavior is limited to per-side cell border provenance and writer downgrade edge summaries.

## Follow-Up

Possible next table geometry work: caption-width placement interactions, writer downgrade summaries for complex table body groups, or additional WordPress table review examples for mixed colgroup and side-border provenance.
