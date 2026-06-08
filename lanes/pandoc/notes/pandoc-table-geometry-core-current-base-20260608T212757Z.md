# Pandoc Table Geometry Core Current-Base: Legacy Table Placement Alignment

Slice: `pandoc-table-geometry-core-current-base-20260608T212757Z`
Base: `f94cd49776af1fdb70407b844387dc497686d765`

## Behavior

- Added a bounded native table-geometry handoff for legacy HTML `<table align="left|right|center">`.
- `TableGeometry::reviewPacket()` now exposes normalized `tableAlignment` metadata and summary fields.
- Markdown, AsciiDoc, and LaTeX writer downgrade packets now report table placement alignment as a writer-review requirement.
- `WordPressBlockWriter` preserves safe table-level `align` values and drops unsupported values without allowing row/section/cell `align` through the generic attribute path.

## Source Truth And Non-Overlap

- This is a native PHP support-library slice under `lanes/pandoc/**`.
- The behavior extends the existing table layout/frame/spacing metadata pattern and does not shell out to Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external writers, browser renderers, online services, live provider tests, or live-service provider tests.
- Non-overlap: this does not repeat the accepted table summary, table width, table spacing, table frame/rules/border, directionality, colgroup, decimal alignment, or global-row coordinate slices.

## Evidence

- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 659 assertions, 1 failures`
  - Failure: legacy table placement alignment metadata was absent.
- Focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 674 assertions, 0 failures`
- Focused table family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2353 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

## Status Delta

- `phpPass`: `1863 -> 1864`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2290 -> 2291`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 174`

## Dependency Closure

No new support component is needed. The slice reuses native MarkdownReader HTML table attribute capture, TableGeometry review-packet and writer-downgrade metadata, and WordPressBlockWriter table rendering.
