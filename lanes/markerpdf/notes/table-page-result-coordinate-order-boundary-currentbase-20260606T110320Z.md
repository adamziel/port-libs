# Table Page-Result Coordinate Order Boundary

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T110320Z`

Base accepted HEAD: `7344a4e71f586163a7f26e45c5c3d1a246701f1a`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` crops each table from the rendered page image before table recognition/assignment in `marker/tables/table.py`: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/tables/table.py
- The no-GPU PHP lane owns the supplied-boundary handoff where upstream-style `ExtractPageResult` envelopes are flattened into recognized table records before `TableRecognizer` translates page-image geometry to crop-local geometry.
- Existing direct table geometry support already understands explicit `x1_x2_y1_y2` bbox order on flat recognized tables. This slice closes the page-result boundary where page-level `bbox_order`, `rows_bbox_order`, `cols_bbox_order`, and `cells_bbox_order` metadata was not propagated to the flattened per-table record.

## Implementation

- `SuppliedDocumentConverter::pageResultGeometryMetadataKeys()` now copies bbox coordinate-order/format metadata from page-result envelopes to flattened recognized table records.
- The focused fixture supplies one upstream-style page result with rows, columns, cells, stale off-crop cells, shared `image_bbox`, and page-level `x1_x2_y1_y2` order metadata. Without the propagation, the table recognizer interprets bbox arrays in the wrong order and the WordPress table is not inserted.
- Added a WordPress smoke that emits the Gutenberg heading/table/paragraph shape plus table page-result review, coordinate-space review, crop-boundary counts, source bbox order, and no-model/no-external-tool flags.

## Red-First Evidence

- Before the source edit, `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultCoordinateOrderBoundaryCurrentBaseTest.php` failed with `String does not contain '| Feature | Status |'` after 1 selected test file and 2 assertions.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php` -> 3 test files, 95 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php` -> 39 test files, 1591 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-page-result-coordinate-order-boundary-currentbase.php` -> emits `page_result_bbox_order_metadata_propagated=true`, `offcrop_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native supplied document converter, upstream page-result flattener, table recognizer geometry localization, tabled-style assignment/Markdown formatting boundary, and WordPress smoke path. Live OCR, Surya/Texify/Torch, tabled model inference, Python/PDFium/PIL rendering, and full upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat direct flat recognized-table coordinate-order support, normalized page-image shared `image_bbox` propagation, assigned crop filtering, scalar spans, band ordering, or crop polygon geometry slices. The new behavior is specifically page-level bbox-order metadata propagation from `ExtractPageResult` envelopes before flattening.
