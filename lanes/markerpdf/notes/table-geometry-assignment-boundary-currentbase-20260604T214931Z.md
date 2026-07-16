# markerPDF table geometry assignment boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260604T214931Z`

Base accepted HEAD: `42dddc08604dab6783842b91ae410655f23b3754`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` crops table images in `marker/tables/table.py::get_table_boxes()`, runs table recognition on those cropped table images, then calls `tabled.assignment.assign_rows_columns(...)` before formatting Markdown:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/tables/table.py
- https://raw.githubusercontent.com/VikParuchuri/tabled/master/tabled/assignment.py

The PHP lane already clipped supplied row/column band review metadata to the table crop. This slice closes the matching assignment boundary: supplied recognition rows/columns are bounded to the crop before row/column matching, and fully off-crop/non-positive cells are excluded from native assignment while partially crossing cell bboxes remain intact for accepted table-local review metadata.

## Implementation

- `TableRecognizer::assignRowsColumns()` now normalizes the assignment image size once, applies the existing table-grid crop boundary to rows/columns before assignment, and filters cells with no positive in-crop area.
- `SuppliedDocumentConverter` now passes table crop image sizes, not full rendered page sizes, into `formatRecognizedTables()` so supplied-recognition assignment uses the same crop-local coordinate space as upstream tabled.
- `wordpress-table-geometry-boundary-currentbase.php` now includes stale supplied cell text beyond the right and bottom crop edges and reports that it is filtered from assigned table text and Markdown.

## Red-first evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds supplied table geometry to crop image before row column assignment
Expected: Header, Status, Images, Ready
Actual: Header, Status, Images, Ready, Stale right edge, Stale below crop
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php
1 test files, 9 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 927 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
php -l lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-geometry-boundary-currentbase.php
```

All lint commands reported no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-table-geometry-boundary-currentbase.php
```

The smoke emitted `assigned_table_texts=["Header","Images","Ready"]`, `offcrop_cells_filtered_from_assignment=true`, `excluded_offcrop_supplied_cell_text=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the accepted named-destination EOF boundary, table row/column grid review clipping, named/numeric bbox normalization, OCR polygon geometry, OCR grid-border conflict review, header rowspan/grid accessibility metadata, or forced-OCR merged table routing. The new bounded behavior is specifically crop-local row/column/cell assignment before Markdown generation and WordPress table output.

## Dependency closure

No new support component is needed. This reuses the native supplied-document converter, table crop planning, table recognizer assignment, existing grid-boundary clipping helper, Markdown table formatter, and WordPress smoke path. Full upstream parity remains intentionally gated by no-GPU scope for live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch OCR/layout/table models, tabled model execution, Texify equation recognition, Streamlit/FastAPI runtime workers, benchmark/model downloads, and external OCR/rendering helpers.
