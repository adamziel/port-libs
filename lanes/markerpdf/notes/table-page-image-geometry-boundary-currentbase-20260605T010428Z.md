# markerPDF table page-image geometry boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T010428Z`
Base accepted HEAD: `0ea8dd0772ccf1520f53c121288a94ef07992eca`

## Source Truth

- Upstream `sddai/markerPDF` pinned in the lane manifest routes tables through `marker/tables/table.py::get_table_boxes()` and `format_tables()`: layout table boxes are rescaled to high-resolution page coordinates, cropped from the rendered page image, then passed as cropped table images to tabled recognition and `assign_rows_columns()`.
- That means tabled row/column/cell geometry is normally table-crop-local. A native supplied-boundary fixture that serializes recognition geometry in rendered page-image coordinates must subtract the current table crop origin before assignment, clipping, span-grid review, and WordPress table formatting.

## Implementation

- `TableRecognizer::formatRecognizedTables()` now localizes recognized table geometry when the table explicitly declares `coordinate_space`, `geometry_coordinate_space`, or per-field coordinate-space metadata as `page_image`/page-like.
- The localizer uses the current crop bbox from the table bundle or from `SuppliedDocumentConverter` table crop metadata, translates rows, columns, cells, and OCR conflict bboxes to table-crop coordinates, and emits `table_recognition_coordinate_space_boundary` review metadata.
- `SuppliedDocumentConverter` now passes each table crop's high-resolution bbox into the recognizer and stores non-null coordinate-space reviews in `metadata.table_coordinate_space_reviews`.
- Table-local recognition bundles keep the existing path unchanged.

## Focused Evidence

- `php -l lanes/markerpdf/src/TableRecognizer.php`
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php`
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-table-page-image-geometry-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => `2 test files, 1045 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-page-image-geometry-boundary-currentbase.php` emitted `page_image_geometry_translated=true`, `offcrop_page_image_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table crop planner, table recognizer, tabled-style row/column assignment, span-grid review, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, tabled model inference, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted table-local crop clipping, named/numeric/reversed bbox normalization, forced-OCR routing, OCR polygon precedence over stale bbox, grid-border conflict review, span/rowspan/colspan review, rotated header axes, or layout-table bbox canonicalization. The new behavior is specifically explicit page-image coordinate recognition geometry being translated to the current table crop before assignment and WordPress table formatting.
