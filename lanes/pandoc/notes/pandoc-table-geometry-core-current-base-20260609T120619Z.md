# Pandoc Table Geometry Core Current Base - Legacy Caption Align

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-table-geometry-core-current-base-20260609T120619Z`
- Base accepted HEAD: `67d434edf3a4d801f81c24c8c2a09230a63f024a`

## Source Truth

Bounded HTML table import behavior: legacy HTML captions may carry `align="top"`,
`align="bottom"`, `align="left"`, or `align="right"` placement intent. Existing
Pandoc-lane table geometry already maps CSS `caption-side` into caption
placement/review metadata, so this slice treats legacy `align` as a fallback
caption-side source only when CSS `caption-side` is absent.

## Implementation

- `MarkdownReader` now records caption-side provenance as `captionSideSource`.
  CSS `caption-side` yields `style`; legacy caption `align` yields `align`.
- `TableGeometry` carries `captionSideSource` into caption records, review packet
  summaries, and markdown/asciidoc/latex writer downgrade diagnostics.
- `WordPressBlockWriter` uses the existing caption placement path: top captions
  render before the table, side captions retain the safe after-table fallback and
  review diagnostic. The obsolete caption `align` attribute remains metadata-only
  in sanitized WordPress output.
- `wordpress-table-geometry-handoff.php --self-test` now covers the visible
  WordPress handoff for legacy top and side caption alignment.

## Evidence

Baseline before the change:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 1455 assertions, 0 failures`

After the change:

- `php -l lanes/pandoc/src/MarkdownReader.php`
  - no syntax errors
- `php -l lanes/pandoc/src/TableGeometry.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 1493 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `35 test files, 55645 assertions, 0 failures`
- `git diff --check -- lanes/pandoc`
  - clean

Status delta:

- `phpPass`: `2756 -> 2757`
- `benchmarkDenominator.mapped`: `2985 -> 2986`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 193`

## Non-Overlap

This does not repeat the accepted CSS `caption-side` top/bottom/left coverage,
caption source attribute sanitization, row/section/cell presentation, column
presentation, source header, scope auto, flat-grid, or doctemplates slices. The
new behavior is specifically legacy HTML `<caption align="...">` fallback
provenance and placement/review handoff.

## Dependency Closure

No new support component is required. The slice reuses existing native PHP
`MarkdownReader`, `TableGeometry`, and `WordPressBlockWriter` support. No Pandoc,
Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, external template
engine, online service, or live-service provider test was invoked.

## Root Harness

Not run - isolated micro-slice.
