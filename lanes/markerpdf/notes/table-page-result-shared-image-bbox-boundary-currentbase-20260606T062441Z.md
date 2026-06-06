# Table Page Result Shared Image Bbox Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T062441Z`

Base: `ff6d9ac7ac50ba24390bdd95da205dfc798a98c3`

## Source Truth

The locked tabled handoff supports page-level `ExtractPageResult` envelopes with
parallel `cells`, `rows_cols`, and `bboxes` lists. Existing native coverage
already handled per-table `image_bboxes`. This slice covers the neighboring
boundary where the page-result envelope carries one shared page `image_bbox`
and scalar coordinate-space metadata for all flattened rows, columns, and
cells.

## Behavior

- `SuppliedDocumentConverter::flattenRecognizedTablePageResults()` now applies
  a page-result shared `image_bbox`, `page_image_bbox`, or
  `rendered_image_bbox` to each flattened table when no per-table
  `image_bboxes[index]` value is present.
- The flattener also carries scalar table geometry coordinate-space metadata
  such as `rows_coordinate_space`, `cols_coordinate_space`, and
  `cells_coordinate_space` into each flattened table record.
- `TableRecognizer` can then normalize `normalized_page_image` geometry against
  the shared page image size and translate it into the table crop before
  assigned-cell filtering and Markdown table insertion.

## Evidence

Red-first before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxBoundaryCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 1 failures`; the Markdown output omitted
the expected `| Feature | Status |` table because the normalized page-image
cells were treated as crop-local geometry and filtered out.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxBoundaryCurrentBaseTest.php`

Result: `1 test files, 25 assertions, 0 failures`.

Focused family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `34 test files, 1383 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-page-result-shared-image-bbox-currentbase.php`

Result: emits `coordinate_status=translated_and_normalized_to_table_crop`,
`shared_image_bbox_source=image_bbox`, `active_cell_count=4`,
`excluded_cell_count=2`, no Python/model execution, and no external PDF tools.

## Non-Overlap

This does not repeat the accepted page-result `image_bboxes` parallel-list
flattening, direct saved table `bbox`/`image_bbox` handling, page-image
geometry localization, normalized page-image geometry localization,
source-bbox fallback, mixed coordinate spaces, wrapped/named/reversed/polygon
bbox aliases, crop-polygon precedence, detector-source boundaries, OCR grid
conflict localization, or assigned-cell crop/band filtering. The bounded
behavior is only page-result envelope metadata propagation before existing
table geometry localization.

## Dependency Closure

No new support component is needed. The patch reuses native PHP supplied
document conversion and table recognition. Live OCR, Surya/Texify/Torch,
tabled model execution, and exact upstream model benchmark parity remain
intentionally out of scope under the current no-GPU markerPDF directive.
