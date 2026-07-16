# Table Geometry Numeric String Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260604T103127Z`
Base accepted HEAD: `0f8a07803dce0dbe38e0dd1e4a9016b7a57f5a80`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes supplied table boxes through `marker/tables/table.py::get_table_boxes` and locked `tabled-pdf==0.1.4`.
- Locked `tabled-pdf==0.1.4` uses Pydantic `surya.schema.Bbox`/`TableResult` rows, columns, and cells before `tabled.assignment.assign_rows_columns()` and span review. At the native PHP supplied-boundary, serialized JSON/API table geometry can arrive as numeric scalar strings even though the semantic geometry is still finite numeric bbox data.
- This is a parser/converter boundary only. It does not run table detection models, OCR, PDFium rendering, or Python.

## Implementation

- `TableRecognizer` now coerces finite numeric strings in:
  - four-value `bbox` arrays;
  - named bbox fields (`x1/y1/x2/y2`, `x_start/y_start/x_end/y_end`, and `left/top/right/bottom`);
  - four-corner OCR/table polygons;
  - table image `width`/`height` inputs.
- Existing assignment, span, crop clipping, and WordPress grid-review behavior is unchanged after normalization.
- Added `wordpress-table-geometry-numeric-string-boundary-currentbase.php` to prove the user-visible supplied-document import path replaces stale pdftext table lines while preserving non-zero row/column IDs and crop-clipped grid bboxes.

## Red/Green Evidence

Red before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php
FAIL coerces serialized numeric string table geometry before crop boundary review
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon.
1 test files, 361 assertions, 1 failures
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php
1 test files, 385 assertions, 0 failures
```

Additional focused integration:

```text
php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 513 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-geometry-numeric-string-boundary-currentbase.php
```

The smoke reported `table_crop_boundary={"width":240,"height":80}`, `clipped_band_count=3`, `excluded_band_count=2`, `grid_positions=["10:20","10:21","11:20","11:21"]`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted table crop clipping/exclusion behavior, named-Bbox field normalization, forced-OCR routing, OCR prediction unwrapping, merged-cell geometry, grid-border conflict assignment, header-grid accessibility IDs, rowspanned caption binding, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically numeric-string geometry coercion before the existing table assignment and crop-boundary review paths.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, `SuppliedDocumentConverter`, table crop planning, and WordPress smoke path. Full upstream parity remains intentionally outside this no-GPU worker because live OCR, Surya/Texify/Torch model execution, visual table recognition, PDFium rendering, Streamlit/FastAPI model workers, benchmark model downloads, and exact upstream model benchmark parity were not run.
