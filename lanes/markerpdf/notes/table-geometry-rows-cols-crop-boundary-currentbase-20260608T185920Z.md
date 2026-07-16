# markerpdf-table-geometry-boundary-current-base-20260608T185920Z

## Source Truth

- Upstream `tabled_pdf-0.1.4/tabled/schema.py` defines `ExtractPageResult` with parallel `cells`, `rows_cols`, `table_imgs`, `bboxes`, and `image_bboxes`.
- Upstream `tabled_pdf-0.1.4/tabled/extract.py` serializes table detection output as per-table `bbox`/`image_bbox` alongside `cells`, `rows`, and `cols`. Saved direct sidecars can therefore preserve the current `TableResult` crop under `rows_cols` while a wrapper-level generic `bbox` is broader or stale.
- This no-GPU slice uses supplied table recognition arrays only. It does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools.

## Implementation Delta

- `TableRecognizer::tableCropBboxCandidate()` now checks a single upstream-style `rows_cols` `TableResult` container for `table_bbox`/`crop_bbox`/`bbox`/source geometry before falling back to the wrapper-level generic `bbox`.
- Crop image-size derivation now preserves `source_coordinate_space` as `table_bbox_coordinate_space` when the derived table crop bbox is copied onto the synthetic image-size record.
- Added a focused supplied-boundary test where stale wrapper `bbox` is the full page but `rows_cols.bbox` is the current table crop. The expected result is table-crop localization, active row/column band filtering, and Markdown without ghost cells.
- Added a WordPress-oriented smoke example proving the direct recognizer prefers `rows_cols.bbox` and the supplied converter still renders the table while excluding stale pdftext.

## Focused Evidence

- Red-first check after fixing test bootstrap: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsCropBoundaryCurrentBaseTest.php` failed before the recognizer change because the expected rows-cols crop-local boundary was not produced.
- Focused test: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsCropBoundaryCurrentBaseTest.php` => 1 test file, 14 assertions, 0 failures.
- Adjacent table-geometry family: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNestedSourceCropCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryGenericCropBboxCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php` => 5 test files, 192 assertions, 0 failures.
- Example smoke: `php lanes/markerpdf/examples/wordpress-table-rows-cols-crop-boundary-currentbase.php` exits 0 with `direct_table_bbox_source=rows_cols.bbox`, `wordpress_table_rendered=true`, and `stale_pdftext_table_line_excluded=true`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP table geometry canonicalizer, supplied converter, and lane test harness.

## Non-Overlap

This is not a model/OCR/table-detection slice. It does not alter page-result flattening for parallel `bboxes`/`rows_cols`, nested `table_image.source_bbox` crop metadata, crop polygons, generic coordinate-space crop bboxes, or PDF parser behavior. It only covers direct single-table `rows_cols` crop metadata winning before stale generic wrapper `bbox`.
