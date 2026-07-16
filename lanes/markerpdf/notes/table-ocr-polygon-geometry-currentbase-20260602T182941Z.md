# Table OCR Polygon Geometry Current Base

Slice: `table-ocr-geometry-currentbase`

Accepted base: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

- Upstream `marker/tables/table.py` routes table conversion through `get_table_boxes()`, table detection/recognition, row/column assignment, and table formatting before Markdown output.
- Locked `tabled-pdf` recognition source uses `recognize_tables()` with `run_recognition(..., bboxes=ocr_cells)` and zips `ocr_pred.text_lines` back onto detector cells.
- Surya `run_recognition()` accepts bbox or polygon OCR geometry and emits `TextLine(text=..., polygon=poly)` records; Surya `TextLine` derives `bbox` from the polygon corners.

## Implementation

- `TableRecognizer` now accepts four-corner `polygon` geometry anywhere it previously required a four-value table/OCR `bbox`.
- OCR text-line polygons derive `[min x, min y, max x, max y]` before geometry assignment, grid-border review, and row/column normalization.
- Supplied-document conversion and the WordPress smoke prove out-of-order OCR text lines map by detector-cell geometry before table Markdown/Gutenberg rendering.

## Red-First Evidence

- Before the source fix, `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` failed the new polygon geometry case because cells remained in OCR source order: `Status`, `Feature`, `Ready`, `Images`; the run reported `1 test files, 170 assertions, 1 failures`.
- Before the source fix, `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed the supplied-document case with `| Status | Feature |` instead of `| Feature | Status |`; the run reported `1 test files, 273 assertions, 1 failures`.

## Verification

- `php -l lanes/markerpdf/src/TableRecognizer.php` - no syntax errors.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` - no syntax errors.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-polygon-geometry-currentbase.php` - no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` - `1 test files, 173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - `1 test files, 282 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - `2 test files, 455 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-ocr-polygon-geometry-currentbase.php` - emitted `geometry_assignment_used=true`, `source_order_would_be_wrong=true`, `grid_border_conflict_review_empty=true`, `excluded_stale_pdftext_table_line=true`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` - passed.
- `git diff --check -- lanes/markerpdf` - passed.

## Delta

- Behavior tests move `640 -> 642 pass / 0 fail`.
- Focused assertions add 12 across the two changed focused test files.
- Mapped semantics move `467 -> 468 / 78`.

## Dependency Closure

No new support component is needed. The slice reuses native supplied-document conversion, forced OCR table routing, table recognition, row/column assignment, Markdown formatting, and the WordPress smoke path. Full upstream parity remains gated by live pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify/model downloads, Streamlit/FastAPI, OCR/raster backends, and external PDF tooling.

## Non-Overlap

This does not repeat accepted forced-OCR prediction unwrapping, merged-cell geometry review, header-axis/spanning-grid review, multiline OCR bbox fragments, or grid-border conflict source-order fallback. The new boundary is serialized upstream OCR `TextLine` polygons without `bbox` values being normalized to bboxes so geometry assignment can override wrong OCR source order.
