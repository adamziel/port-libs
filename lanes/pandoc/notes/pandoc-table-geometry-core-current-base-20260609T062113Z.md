# Pandoc Table Geometry Cell Dimensions

Slice: `pandoc-table-geometry-core-current-base-20260609T062113Z`
Base: `92533a92bda5fcca4bf5b10d8bb594be7e689c42`
Date: 2026-06-09 UTC

## Behavior

- Added native table-geometry review-packet metadata for safe HTML table cell `width` and `height` dimensions.
- Records covered visual columns, section/row provenance, header-cell status, text, canonical width/height values, value types, source attributes, and whether dimensions came from HTML attributes or `style`.
- Added summary rollups and markdown/asciidoc/latex downgrade diagnostics for cell dimensions that cannot round-trip without raw HTML or reviewer comments.
- Extended the WordPress table handoff smoke so valid cell dimensions remain visible while invalid `width="0"` is omitted.

## Evidence

- Focused check: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 1280 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP HTML reader, `TableGeometry` cell coverage records and table dimension normalizers, `WordPressBlockWriter` table attribute preservation, and the focused lane TestRunner. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted table geometry support for column backgrounds, column borders, column decimal alignment, cell decimal alignment, cell nowrap, table/section/row/cell background or border presentation, source header target geometry, directionality, table layout/frame/spacing/background, or DOCX/ODF table-style imports. It covers only HTML table cell width/height dimensions and their WordPress handoff.
