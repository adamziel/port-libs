# Pandoc Table Geometry Core Current Base - Invalid Width Provenance

Date: 2026-06-05 UTC
Base accepted HEAD: `06e075173567b88f100569de56d159a9c3ce681d`
Micro-slice: `pandoc-table-geometry-core-current-base-20260605T151452Z`

## Source Truth

- Reused the accepted native table-geometry contract mapped from Pandoc table fixtures and reader handoffs already recorded in `UPSTREAM_TEST_MANIFEST.json`, including `test/pipe-tables.txt`, `test/tables.markdown`, `test/tables.native`, `test/html-reader.html`, `test/html-reader.native`, and `test/tables/nordics.html5`.
- No local hydrated upstream Pandoc checkout was available in `.upstream-cache/pandoc`, and no Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external writer, browser renderer, online sanitizer, or online service was executed.

## Implementation

- `TableGeometry::columnWidthSummary()` now preserves valid width columns separately from invalid width columns and missing/default columns.
- Invalid source width values such as non-numeric strings and negative numbers are retained as bounded provenance records with `column`, `rawType`, and scalar `rawValue`.
- `TableGeometry::diagnostics()` now emits `table-widths-have-invalid-values` without suppressing the existing overfull-width diagnostic.
- WordPress table output still suppresses `<colgroup>` for incomplete or invalid partial width sets, avoiding misleading import geometry while keeping usable alignment and table content.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 824 assertions, 0 failures`
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - failed after adding the invalid-width case with `1 test files, 591 assertions, 1 failures`
- Final focused family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 845 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

Delta: +1 focused PASS case and +21 focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `AstNode`, `TableGeometry`, `MarkdownReader`, and `WordPressBlockWriter` support-library surface. Full upstream runner parity remains gated on hydrating the pinned Pandoc checkout and producing a non-mutating Cabal plan for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap And Follow-Up

- Avoided accepted table geometry clusters for visual spans, colspec columns, row-head columns, section-boundary rowspans, declared-column overflow, nested table packets, accessibility metadata, and RST/AsciiDoc writer requirements.
- Follow-up should keep automatic width repair, CSS layout/cascade, richer DOCX/ODT table style rendering, and full Pandoc writer parity as separate bounded slices.
