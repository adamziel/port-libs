# markerPDF table rows_cols boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260607T111551Z`

Base accepted HEAD: `f0ab63b0aec4070b72a5ad36f42b8b417227d7b2`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates table extraction to tabled.
- Locked tabled-pdf 0.1.4 source under `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4` shows `ExtractPageResult` carries `cells`, `rows_cols`, `bboxes`, and `image_bboxes`.
- `tabled.extract.extract_tables()` stores the output of `assign_rows_columns()` in `ExtractPageResult.cells` and the recognizer `TableResult` rows/columns in `ExtractPageResult.rows_cols`.
- `tabled.inference.recognition.recognize_tables()` returns `TableResult` objects with `rows` and `cols`; a supplied single-table sidecar can preserve that wrapper under `rows_cols` instead of flattening it to top-level `rows`/`cols`.

No Python, PDFium, PIL, Surya, OCR, tabled model inference, Torch, GPU, or external PDF tools were executed.

## Implementation

- `TableRecognizer::canonicalizedRecognizedTableGeometryAliases()` still prefers canonical top-level `rows`/`cols`, then accepted flat aliases such as `row_bboxes` and `columns`.
- Added a final fallback for a single supplied `rows_cols` `TableResult` container, either associative (`rows_cols.rows` / `rows_cols.cols`) or a one-item list (`rows_cols.0.rows` / `rows_cols.0.cols`).
- The fallback records `rows_source_alias` and `cols_source_alias` with the nested path and carries coordinate-space or bbox-order metadata from the wrapper when no field-specific top-level metadata is already present.
- Added a focused converter test proving nested `rows_cols` rows/columns are localized from page-image to table-crop coordinates before row/column assignment and WordPress spanning-grid review.
- Added a local WordPress smoke for the supplied-document conversion path; it verifies the stale pdftext table line is replaced and the Header cell preserves upstream row id `10`, column ids `20,21`, and colspan `2`.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL canonicalizes single table rows_cols wrapper before geometry assignment (lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'rows_cols.rows'
Actual: NULL
FAIL surfaces single table rows_cols wrapper through supplied WordPress conversion (lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 10,
)
Actual: array (
  0 => 0,
)

1 test files, 12 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-rows-cols-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-rows-cols-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php
1 test files, 31 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
47 test files, 1899 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-rows-cols-boundary-currentbase.php
exits 0 and reports rows_cols_geometry_preserved=true, stale_pdftext_table_line_excluded=true, assigned_header_row_ids=[10], assigned_header_col_ids=[20,21], assigned_header_colspan=2, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native supplied-document converter, table recognizer, table crop geometry localizer, row/column assignment, spanning-grid Markdown formatter, and WordPress smoke path. Live OCR, Surya/Texify/Torch, tabled model execution, Python/PDFium/PIL rendering, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat page-result flattening of parallel `rows_cols` lists, flat `row_bboxes`/`columns` aliases, coordinate-order propagation, page-image crop localization, normalized page-image geometry, nested crop metadata, mixed assigned-cell filtering, active row/column band filtering, scalar spans, OCR conflict localization, or stream/filter/parser behavior. The bounded behavior is only a direct single-table supplied sidecar that keeps one upstream `TableResult` nested under `rows_cols` and needs its rows/columns exposed before native crop-boundary assignment.
