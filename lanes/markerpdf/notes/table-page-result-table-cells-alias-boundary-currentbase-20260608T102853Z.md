# markerpdf table page-result table_cells alias boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T102853Z`
Base accepted HEAD: `6c009f4b63e232febe2df2538598096a435fd432`

## Source truth

- Locked local tabled-pdf source: `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4`.
- `tabled/schema.py` defines `ExtractPageResult.cells` as a list of table cell lists parallel with `rows_cols`, `table_imgs`, `bboxes`, and `image_bboxes`.
- The native PHP table cell router already emits grouped recognized cell lists as `table_cells`, so supplied sidecars may reach the ExtractPageResult boundary with that alias rather than canonical `cells`.

## Behavior

- `SuppliedDocumentConverter` now recognizes `table_cells` as a bounded alias for page-level ExtractPageResult grouped cell lists.
- Canonical `cells` remains preferred when present.
- `table_cells_coordinate_space`, order, and format metadata are copied to canonical `cells_*` geometry keys before `TableRecognizer` localizes the page-image geometry to the table crop.
- Inactive row/column cells are still removed by the existing assigned-band boundary before Markdown/WordPress table output.

## Evidence

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/TableGeometryPageResultTableCellsAliasBoundaryCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-table-page-result-table-cells-alias-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultTableCellsAliasBoundaryCurrentBaseTest.php`
  - `1 test files, 32 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResult*CurrentBaseTest.php`
  - `9 test files, 293 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-page-result-table-cells-alias-currentbase.php`
  - `table_cells_alias_flattened=true`
  - `stale_pdftext_table_line_excluded=true`
  - `inactive_alias_cells_filtered=true`
  - `executes_python_or_models=false`
  - `executes_external_pdf_tools=false`

## Dependency closure

No new support component is needed. This reuses the existing native supplied-boundary table recognizer, row/column assignment, crop-localization, and Markdown formatter paths.

## Non-overlap

This patch does not work on live OCR, Surya/Texify/Torch, GPU/model execution, Streamlit/FastAPI workers, or upstream benchmark parity. It avoids the accepted AcroForm Rect operand and broader PDF parser/security/image/form batches and only touches the supplied table page-result flattening boundary.
