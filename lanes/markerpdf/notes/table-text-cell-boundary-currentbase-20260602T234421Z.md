# Table Text Cell Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/tables/table.py::get_table_boxes` crops each merged table layout box from the high-resolution rendered page image, then passes those high-resolution table boxes plus pdftext text-line dictionaries into `tabled.inference.recognition::get_cells`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::get_cells` calls `surya.input.pdflines::get_table_blocks([highres_bbox], text_line, image_size)[0]` when a table has usable pdftext lines, and falls back to detector/OCR cells only when text lines are absent, forced, or empty.
- Locked `surya-ocr==0.6.10` `surya/input/pdflines.py::get_table_blocks` filters lines by table overlap, splits chars into cell text spans, then subtracts the input table bbox origin so returned cell coordinates are table-crop local.

## Implementation

- `TableRecognizer::getCells()` now returns `table_text_cell_boundary_reviews` aligned with `table_cells` and `needs_ocr`.
- The review rows preserve upstream returned cell bboxes unchanged for recognition/Markdown, while exposing bounded WordPress metadata for crop-crossing cells:
  - `review_target=table_text_cell_geometry_boundary`
  - `table_crop_size`
  - clipped/within/outside cell counts
  - per-cell original, clipped, and bounded bboxes
  - `upstream_cell_bbox_retained=true`
- `SuppliedDocumentConverter` threads non-empty reviews into `metadata.table_text_cell_boundary_reviews`.
- Added `wordpress-table-text-cell-boundary-currentbase.php` to prove the user-visible WordPress path replaces stale pdftext table text while retaining clipped cell-boundary review metadata without Python/models/external PDF tools.

## Verification

- `php -l lanes/markerpdf/src/TableRecognizer.php`
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php`
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-table-text-cell-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `2 test files, 834 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-text-cell-boundary-currentbase.php` passed and reported crop size `358 x 80`, one clipped cell, upstream bbox retention, stale table-line exclusion, and no Python/model/external PDF-tool execution.

## Dependency Closure

No new support component is needed. This slice reuses native supplied-document conversion, table crop planning, pdftext dictionary line splitting, tabled-style supplied recognition, and Markdown table formatting. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf model inference, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were executed.

## Non-Overlap

This does not repeat accepted table row/column band clipping, merged table boundaries, forced-OCR routing, OCR prediction unwrapping, table cursor/grid-border conflict assignment, header-grid accessibility, caption/span-grid binding, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically review-only crop-boundary metadata for pdftext-derived table cells while preserving upstream table-local cell bboxes for recognition.
