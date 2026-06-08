# Table Page Result Shared Image Bbox Order Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T111328Z`

Base: `e197436967449f47cc9be6a918a5b4cf8f4f2dcf`

## Source Truth

Upstream markerPDF at the manifest-pinned commit routes table recognition
through tabled page-level `ExtractPageResult` envelopes, then crops each table
from the rendered page image before assignment. The no-GPU PHP lane owns the
supplied-boundary handoff for serialized page-result geometry before any live
Surya/tabled model execution.

Existing native coverage already propagated a shared page-result `image_bbox`
for normalized page-image rows, columns, and cells. This slice closes the
neighboring coordinate-order boundary: when the shared page image bbox is
serialized as `x1,x2,y1,y2`, the flattener must canonicalize it before the
recognizer uses that bbox as the page-image normalization denominator.

## Behavior

- `SuppliedDocumentConverter::flattenRecognizedTablePageResults()` now applies
  source-key coordinate-order metadata to page-result shared `image_bbox`,
  `page_image_bbox`, and `rendered_image_bbox` values before attaching them to
  flattened table records.
- The same helper is used for indexed `image_bboxes[index]` values, preserving
  existing behavior while allowing per-entry or page-level image-bbox order
  metadata.
- Normalized page-image table rows, columns, and cells now scale against the
  real rendered page size, translate to the table crop, and filter stale
  off-crop cells before WordPress Markdown table insertion.

## Evidence

Red-first before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 1 failures`; the Markdown output omitted
`| Feature | Status |` because the ordered shared image bbox collapsed to a
zero-width page-image denominator.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 24 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-page-result-shared-image-bbox-order-currentbase.php`

Result: exits 0 and emits `coordinate_status=translated_and_normalized_to_table_crop`,
`shared_image_bbox_source=image_bbox`, `page_image_normalization_size={612,792}`,
`assigned_table_texts=["Feature","Status","Images","Ready"]`,
`inserted_tables=1`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-result table bbox aliases, table cells
aliases, coordinate-order propagation for rows/cols/cells, shared image bbox
presence, normalized page-image geometry, generic extent/named/polygon/wrapped
geometry aliases, table image crop metadata, OCR grid conflict localization,
or assigned-cell crop/band filtering. The bounded behavior is only
coordinate-order canonicalization for page-result image bboxes before existing
normalization/localization.

## Dependency Closure

No new support component is needed. The slice reuses native PHP supplied
document conversion, page-result flattening, table geometry localization, table
assignment filtering, Markdown formatting, and the WordPress smoke path. Live
OCR, Surya/Texify/Torch/tabled model execution, PDFium rendering, and exact
upstream model benchmark parity remain intentionally out of scope under the
current no-GPU markerPDF directive.
