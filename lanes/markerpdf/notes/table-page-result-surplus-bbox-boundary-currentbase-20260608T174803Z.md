# markerpdf table page-result surplus bbox boundary current-base

- Slice: `markerpdf-table-geometry-boundary-current-base-20260608T174803Z`
- Base accepted HEAD: `9965dd418ac9194ca9784a6dc4cecce9c13d164f`
- Upstream source truth:
  - `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4/tabled/schema.py`
    `ExtractPageResult` stores `cells: List[List[SpanTableCell]]`, `rows_cols`,
    `table_imgs`, `bboxes`, and `image_bboxes`; its validator ties table count
    to `cells`, `rows_cols`, and `table_imgs`.
  - `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4/tabled/extract.py`
    slices table cells and bbox sidecars from page-level extraction results.
- Behavior ported: PHP page-result flattening now treats
  `ExtractPageResult.cells` as the authoritative per-table count. Surplus
  `rows_cols`, table bbox, image bbox, or table-image sidecars are reported in
  `table_page_result_boundary_reviews` and do not create empty ghost tables.
- Red-first evidence: the new focused test failed before the source change with
  `Missing supplied detector cells for table index 0.` because a third surplus
  bbox created a third table record without cells.
- Focused verification:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultSurplusBboxBoundaryCurrentBaseTest.php`
    => 1 test file, 16 assertions, 0 failures.
  - `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/TableGeometryPageResult.*Test\\.php$' | sort)`
    => 11 selected test files, 333 assertions, 0 failures.
  - `php lanes/markerpdf/examples/wordpress-table-page-result-surplus-bbox-boundary-currentbase.php`
    => exits 0; reports `inserted_tables=2`,
    `surplus_table_bbox_count=1`, `surplus_image_bbox_count=1`,
    and `ghost_table_records_suppressed=true`.
- Dependency closure: no new support component is needed. This reuses the
  existing native PHP supplied-boundary converter, table formatter, and table
  recognizer path; no Python, OCR, GPU/model worker, raster execution, or
  external PDF tool is required.
- Root harness: not run - isolated micro-slice.
- Non-overlap: this does not repeat accepted table rows/cols geometry,
  bbox-order alias, saved-result, table image crop, OCR-source bbox, scalar
  span, or annotation/link boundary slices. It only fixes the page-level
  `ExtractPageResult` sidecar-count boundary.
