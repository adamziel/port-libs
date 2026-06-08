# MarkerPDF Singular Rows/Columns Alias Table Boundary Current-Base Slice

Date: 2026-06-08 UTC

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T182411Z`

Accepted base: `0ca1726be1212764e1653162e91e283c2a5975b7`

## Source Truth

- Upstream table handoff source truth for this no-GPU lane is the supplied table boundary: `ExtractPageResult` carries per-table `cells` parallel with `rows_cols`, table bboxes, and page image bboxes before markerPDF formats tables.
- Existing native metadata readers already accepted singular `row_bbox_*` and `column_bbox_*` coordinate-space/order keys, so this slice makes the row/column band record containers match that same supplied-sidecar alias boundary.
- Non-overlap: this does not repeat plural `row_bboxes`/`columns`, `rows_cols.rows`/`rows_cols.cols`, page-result coordinate-order propagation, saved TableResult default order, span-stop behavior, table-cell aliases, or any live table detection/OCR/model execution.

## Behavior

- `SuppliedDocumentConverter` now flattens `ExtractPageResult.rows_cols` entries whose row bands are supplied as `row`, `row_bbox`, `row_box`, or `row_bound`, and whose column bands are supplied as `column`, `col`, `column_bbox`, `col_bbox`, `column_box`, or `col_box`.
- `TableRecognizer` now canonicalizes the same singular aliases for direct flat supplied table records and nested `rows_cols` wrappers.
- Existing plural aliases keep precedence. Existing coordinate metadata, crop-local translation, active band clipping, spanning-grid review, and Markdown insertion behavior are unchanged.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySingularRowsColsAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 6 assertions, 2 failures`; direct flat singular aliases left `rows_source_alias` null, and the page-result singular alias path produced no Markdown table.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySingularRowsColsAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 51 assertions, 0 failures`.

Adjacent table alias run:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryFlatAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultRowsColsAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySingularRowsColsAliasBoundaryCurrentBaseTest.php`

Result: `4 test files, 158 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-singular-rows-cols-alias-boundary-currentbase.php`

Result: exits 0 with `singular_aliases_flattened=true`, `markdown_table_preserved=true`, `stale_pdftext_and_offband_cells_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and hygiene:

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/src/TableRecognizer.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometrySingularRowsColsAliasBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-singular-rows-cols-alias-boundary-currentbase.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: `lane-status json ok`.
- `git diff --check -- lanes/markerpdf`: clean.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SuppliedDocumentConverter`, `TableRecognizer`, crop-localization, band assignment, and Markdown formatter paths. GPU/model OCR, Surya, tabled runtime inference, Python, PDFium, PIL, raster table detection, and external PDF tools remain intentionally out of scope.

## Next

Continue non-overlapping no-GPU markerPDF work around native searchable-PDF parser behavior and supplied-boundary table/equation handoffs. Remaining model/OCR parity is a documented scope limit, not a blocker for this slice.
