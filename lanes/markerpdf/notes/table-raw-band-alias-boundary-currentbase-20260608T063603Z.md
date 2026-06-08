# MarkerPDF Raw Band Alias Table Boundary Current-Base Slice

Date: 2026-06-08 UTC

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T063603Z`

Accepted base: `1e71f3c93d1cbd08cf1009ae6a57b995bf2b94fc`

## Source Truth

- Upstream markerPDF table conversion crops page images before handing table geometry into tabled row/column assignment and Markdown formatting.
- The locked no-GPU markerPDF lane owns supplied-boundary handoffs, not live Surya/tabled model execution. This slice handles native sidecar geometry where `row_bboxes` and `columns` arrive as direct four-value arrays plus table-level bbox-order metadata.
- Non-overlap: this does not repeat page-result coordinate-order flattening, wrapped/named bbox aliases, rows_cols wrappers, normalized/page-image table crops, or model/OCR table recognition. It only adds the raw indexed row/column band record shape at the existing bbox parser boundary.

## Behavior

- `TableRecognizer::bboxFromRecord()` now accepts records whose own integer keys `0..3` form a bbox.
- Existing table-level coordinate-order propagation is preserved, so raw direct bands with `row_bboxes_bbox_order` / `columns_bbox_order` such as `x1_x2_y1_y2` still normalize before page-image to table-crop translation.
- Source review metadata remains visible as `bbox_array_x1_x2_y1_y2_order`, and stale off-crop row/column bands plus their cells are excluded before WordPress Markdown output.

## Verification

Red-first before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRawBandAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 0 assertions, 2 failures` with raw indexed row/column band records rejected as missing bboxes.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRawBandAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 45 assertions, 0 failures`.

Focused table geometry family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `57 test files, 2274 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-raw-band-alias-boundary-currentbase.php`

Result: exits 0 with `raw_band_aliases_localized=true`, `offcrop_raw_band_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and hygiene:

- `php -l lanes/markerpdf/src/TableRecognizer.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometryRawBandAliasBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-raw-band-alias-boundary-currentbase.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: `lane-status json ok`.
- `git diff --check -- lanes/markerpdf`: clean.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `TableRecognizer`, `SuppliedDocumentConverter`, and table formatting/review stack. GPU/model OCR, Surya, tabled runtime inference, Python, PDFium, PIL, and external PDF tools remain intentionally out of scope.

## Next

Continue non-overlapping no-GPU markerPDF work around native searchable-PDF parser behavior and supplied-boundary table/equation handoffs. Remaining model/OCR parity is a documented scope limit, not a blocker for this slice.
