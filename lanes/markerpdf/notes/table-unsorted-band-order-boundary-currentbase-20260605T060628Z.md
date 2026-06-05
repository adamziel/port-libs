# markerPDF table unsorted band-order boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T060628Z`
Base accepted HEAD: `a554ec7ad7a3cd881f170989ea7f07abd9f4a486`

## Source Truth

- Upstream `sddai/markerPDF` pinned in the lane manifest routes tables through `marker/tables/table.py::get_table_boxes()`: layout table boxes are rescaled to high-resolution page coordinates, cropped from the rendered page image, then sent to tabled recognition/assignment as table-crop-local images.
- Locked tabled assignment works from row/column geometry inside that crop. Native supplied fixtures can serialize arbitrary row/column ids and stale source order, so the PHP boundary must order active row bands top-to-bottom and active column bands left-to-right before Markdown and WordPress grid review.

## Red Probe

Before the change, a current-base PHP probe with row ids `[20, -5]` supplied bottom-before-top and column ids `[100, -10]` supplied right-before-left returned:

```json
{
  "rows": [20, -5],
  "cols": [100, -10],
  "markdown": "| Ready  | Images  |\n|--------|---------|\n| Status | Feature |"
}
```

That inverted the WordPress table even though the crop-local bboxes identified Feature/Status as the physical header row.

## Implementation

- `TableRecognizer::tableGridGeometryBoundary()` now sorts active row and column bands by crop geometry after clipping and before assignment, span review, Markdown formatting, and already-assigned band trimming.
- Normal tables sort rows on `y` and columns on `x`; rotated tables keep the existing rotated-axis model and sort rows on `x` and columns on `y`.
- The geometry boundary review now records `active_row_ids`, `active_col_ids`, `row_sort_axis`, `col_sort_axis`, `row_band_order_normalized`, `col_band_order_normalized`, and per-band `geometry_order` / `geometry_sort_axis`.
- `wordpress-table-unsorted-band-order-boundary-currentbase.php` proves the stale source-order table line is replaced by a correctly ordered Gutenberg table without running Python, OCR, Surya, tabled models, PDFium/PIL, or external PDF tools.

## Focused Evidence

- `php -l lanes/markerpdf/src/TableRecognizer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-unsorted-band-order-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php` => `1 test files, 66 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => `12 test files, 1516 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-unsorted-band-order-boundary-currentbase.php` emitted `row_band_order_normalized=true`, `col_band_order_normalized=true`, `active_row_ids=[-5,20]`, `active_col_ids=[-10,100]`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table crop planner, table recognizer, tabled-style assignment, span-grid review, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rendering, tabled model inference, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted page-image coordinate translation, normalized 1000-unit geometry, table-local crop clipping, named/numeric/reversed bbox normalization, already-assigned active-band filtering, forced-OCR routing, OCR polygon precedence, grid-border conflict review, rowspan/colspan review, rotated header axes, or layout-table bbox canonicalization. The bounded behavior is specifically stale supplied row/column band array order being normalized from crop-local geometry before table assignment and WordPress table rendering.
