# Table Geometry Duplicate Span ID Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T080304Z`

Accepted base: `3ce1ddaf86364b7c1332f264ea0b1bfd575a80dc`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` crops rendered page images before table recognition and formats tabled-recognized cells into Markdown.
- Locked tabled-pdf `SpanTableCell` semantics carry row/column grid occupancy as `row_ids` and `col_ids`; duplicate serialized ids are not distinct grid cells and must not inflate WordPress rowspan/colspan review.
- Native no-GPU scope uses supplied table-recognition rows/cells and does not run Surya, OCR, tabled models, Python, PDFium, pypdfium/PIL, or external PDF tools.

## Implementation

- `TableRecognizer::assignmentIdList()` now deduplicates non-null row/column ids while preserving invalid/null-anchor behavior for fail-closed assigned-cell validation.
- Saved tabled `row_ids`/`col_ids` with duplicate ids now enter band-boundary review, Markdown formatting, and `spanningGridReview()` as set-like ordered grid occupancy.
- Added a focused current-base test covering direct recognizer formatting and supplied WordPress conversion.
- Added `wordpress-table-duplicate-span-id-boundary-currentbase.php` as a self-checking WordPress table smoke.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDuplicateSpanIdBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL deduplicates supplied SpanTableCell row and column ids before span review metadata
Values are not identical
Expected: array (
  0 => 0,
)
Actual: array (
  0 => 0,
  1 => 0,
)
FAIL surfaces duplicate span id cleanup through supplied WordPress conversion
String does not contain '| Feature Status |        |'
1 test files, 10 assertions, 2 failures
```

## Verification

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDuplicateSpanIdBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS deduplicates supplied SpanTableCell row and column ids before span review metadata
PASS surfaces duplicate span id cleanup through supplied WordPress conversion
1 test files, 34 assertions, 0 failures
```

Adjacent table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*Test.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 60 selected test files (root lock skipped)
60 test files, 3521 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-duplicate-span-id-boundary-currentbase.php
```

The smoke reports `deduped_header_span=true`, `deduped_data_cells=true`,
`header_colspan=2`, `header_grid_cell_count=2`,
`excluded_stale_pdftext_table_lines=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax/status checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryDuplicateSpanIdBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-duplicate-span-id-boundary-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
```

All returned clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted crop-boundary clipping, assigned-band trimming,
duplicate row/column band exclusion, center/extent alias localization, OCR
grid-border conflict geometry, forced-OCR merged-cell routing, table header
accessibility review, or PDF parser/xref behavior. The new behavior is
specifically duplicate saved `SpanTableCell` row/column id normalization before
WordPress table span metadata and Markdown handoff.

## Dependency Closure

No new support component is needed. This slice reuses the native supplied
document converter, table recognizer, crop/band boundary review, Markdown table
formatter, and WordPress smoke path. Full upstream model parity remains outside
the current no-GPU scope: live `pdftext`, pypdfium/PDFium rendering, Surya/Torch
OCR/layout/table models, tabled model execution, Texify equation recognition,
Streamlit/FastAPI workers, benchmark/model downloads, and external
OCR/rendering helpers were not run.
