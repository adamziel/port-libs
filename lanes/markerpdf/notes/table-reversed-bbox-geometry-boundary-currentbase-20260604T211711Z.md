# markerpdf reversed bbox table geometry boundary current base

Slice: `markerpdf-table-geometry-boundary-current-base-20260604T211711Z`

Base: `1480bbab70b54431a9debcd67786a4a112caa532`

## Source truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates table extraction to the locked `tabled-pdf` dependency after cropping merged table layout boxes from high-resolution page images.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::get_cells()` filters detector bboxes by positive `area`, and `tabled/assignment.py::assign_rows_columns()` assigns `SpanTableCell` rows/columns with `Bbox(...).intersection_pct(...)`.
- This PHP slice keeps that native supplied-boundary behavior for serialized row, column, and cell bbox arrays whose x/y endpoints arrive reversed. WordPress review metadata needs canonical axis-aligned extents before crop clipping, active-band filtering, grid bbox construction, and Markdown table replacement.

## Implementation

- `TableRecognizer` now canonicalizes positional bbox arrays and named bbox fields to `[minX, minY, maxX, maxY]` at the geometry parsing boundary.
- Row/column geometry review rows now include `endpoint_order_normalized=true` only when the supplied raw endpoint order was reversed.
- Existing coordinate-source labels, polygon-derived bboxes, named bbox normalization, numeric-string coercion, crop-local clipping, and out-of-crop exclusion behavior remain intact.
- Added `examples/wordpress-table-reversed-bbox-geometry-boundary-currentbase.php` to prove the supplied-document WordPress path replaces stale pdftext table text while retaining canonicalized grid review metadata without Python/model/external PDF tools.

## Verification

Red-first:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php`
- Result: `1 test files, 386 assertions, 1 failures`; the new reversed-endpoint test failed because assigned cell bboxes stayed `[x2,y2,x1,y1]`.

After patch:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php`
- Result: `1 test files, 405 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-reversed-bbox-geometry-boundary-currentbase.php`
- Result: emitted `active_band_counts={"rows":2,"cols":2}`, `clipped_band_count=2`, `excluded_band_count=2`, endpoint-normalized flags, canonical grid bboxes, stale pdftext exclusion, and native-only execution flags.

## Dependency closure

No new support component is needed. This reuses the native supplied-document converter, table recognizer, crop-boundary review path, tabled-style assignment, Markdown formatter, and WordPress smoke path. Live OCR, Surya/Torch/tabled model execution, pdftext/PDFium rendering, and exact upstream model benchmark parity remain out of scope under the current no-GPU markerPDF direction.

## Non-overlap

This does not repeat accepted table crop clipping/exclusion, named bbox fields, numeric-string bboxes, OCR polygon geometry, forced-OCR routing, merged-cell geometry, span-grid/header accessibility, grid-border conflict review, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically canonicalizing reversed bbox endpoint order before current-base table assignment and WordPress grid review.
