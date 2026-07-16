# Pandoc Table Geometry Column Border Presentation

Slice: `pandoc-table-geometry-core-current-base-20260609T055102Z`
Base: `0f5df40680da5ed9191360998ab90d0db36f1bca`
Date: 2026-06-09 UTC

## Behavior

- Added native table-geometry review-packet metadata for safe HTML column border presentation from `<colgroup>` and `<col>` sources.
- Records covered visual columns, source element provenance, sanitized `border-color`, `border-style`, `border-width`, side-border edges, source attributes, and summary rollups.
- Added markdown/asciidoc/latex downgrade diagnostics for column border presentation that cannot round-trip without raw HTML or reviewer comments.
- Extended WordPress table output so generated `<col>` elements carry safe source column border styles while unsafe `border-image:url(...)` input is omitted.

## Evidence

- Red-first check: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` failed the new case with `Expected: 2`, `Actual: 0` column border presentation records.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 1238 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP HTML table reader, `TableGeometry` column source grouping, existing table border normalizers, `WordPressBlockWriter` safe style serialization, and focused lane TestRunner. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted table geometry support for column backgrounds, column decimal alignment, table/section/row/cell border presentation, cell side borders, source header target geometry, directionality, table layout/frame/spacing/background, or DOCX/ODF table-style imports. It covers only column-level border presentation from HTML colgroup/col sources and its WordPress handoff.
