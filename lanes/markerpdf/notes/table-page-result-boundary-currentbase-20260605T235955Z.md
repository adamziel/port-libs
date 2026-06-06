# markerPDF Table Page Result Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T235955Z`

Accepted base: `852e8f056e13190ebcce19b66fa7c09c4dd612cc`

## Source Truth

Upstream `tabled.schema.ExtractPageResult` is a page-level table handoff with
parallel `cells`, `rows_cols`, `bboxes`, and `image_bboxes` lists. Each index is
one table crop on the page. Upstream `tabled.extract.extract_tables` later
serializes that envelope into per-table records with `cells`, `rows`, `cols`,
`bbox`, `image_bbox`, `pnum`, and `tnum`.

The native supplied-boundary converter already handled the per-table records,
but a caller that passed the page-level `ExtractPageResult` shape caused the
one page result to be counted as one recognized table while layout had multiple
table crop boxes.

## Implementation

`SuppliedDocumentConverter` now normalizes upstream page-level table results
before table recognition. It detects only the grouped shape where `cells` is a
list of table cell lists and `rows_cols` is present, then flattens the page
envelope into per-table records. Existing flat table recognition records remain
unchanged.

The converter records `table_page_result_boundary_reviews` with the upstream
boundary name, source page result index, flattened recognized-table indexes,
and source list counts for `cells`, `rows_cols`, `bboxes`, and `image_bboxes`.

`TableFormatter` also preserves table order when multiple table blocks on the
same page are removed before Markdown insertion. Earlier insertions now offset
later insertion points, so two upstream page-result tables with adjacent stale
pdftext blocks stay in page-result order instead of being reversed after block
removal.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultBoundaryCurrentBaseTest.php`

Result before source edit: `Recognized table and image size counts must match.`

Focused passing run after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultBoundaryCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`

WordPress example smoke:

`php lanes/markerpdf/examples/wordpress-table-page-result-boundary-currentbase.php`

Expected evidence keys: `inserted_tables=2`, `stale_pdftext_table_lines_removed=true`,
`offcrop_cells_filtered_from_assignment=true`, and both execution flags false.

## Dependency Closure

No new dependency or support component is required. This reuses the existing
native supplied-document converter, table formatter, and table recognizer. OCR,
Surya/Texify/Torch, GPU/model execution, visual table recognition, and exact
upstream model benchmark parity remain intentionally out of scope under the
current no-GPU markerPDF override.

## Non-Overlap

This slice does not change crop-local row/column/cell clipping, endpoint alias
normalization, nested crop metadata, normalized page-image coordinates, OCR
cell routing, or detector-crop behavior. It only bridges the upstream
page-level table-result envelope to the already-supported per-table geometry
records.
