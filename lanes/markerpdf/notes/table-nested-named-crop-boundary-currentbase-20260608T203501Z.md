# Table Nested Named Crop Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T203501Z`

Accepted base: `e76c4cc82ad1172514b0791041ad64c954f9e499`

## Source Truth

- Upstream marker table conversion crops rendered page images before table assignment, then formats assigned rows, columns, and cells as Markdown. Source reference used for this slice: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/tables/table.py
- Tabled/adapter sidecars can serialize crop-table geometry on wrapper records. Existing markerPDF tests already covered nested `source_bbox` and `original_bbox`; this slice covers the remaining current-base gap where the nested crop wrapper itself is a named rectangle (`left`/`top`/`width`/`height`, `x`/`y`/`w`/`h`, etc.).
- This stays inside markerPDF's no-GPU scope: supplied recognition geometry only; no Surya, tabled model inference, OCR, Python, PDFium, PIL, Torch/CUDA, or external PDF tools are run.

## Red-First Evidence

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedNamedCropBoundaryCurrentBaseTest.php`

Initial result before the source change: `1 test files / 15 assertions / 2 failures`.

The direct recognizer case reported `missing_table_crop_bbox` for a nested `table_image` wrapper whose crop rectangle was encoded directly as named fields, and the supplied WordPress conversion lost the expected localized render-cell source bbox.

## Implementation

- `TableRecognizer::nestedTableCropBboxCandidate()` now treats nested crop wrappers as rectangle records when `bboxFromNamedFields()` can read named corner/extent/center/point-pair fields from the container itself.
- The source key is preserved in review metadata as `<container>.bbox_left_top_width_height_fields` or the matching named-field source, and the same wrapper's generic `coordinate_space`/`geometry_space` metadata is honored.
- Existing explicit nested `table_bbox`, polygon, `source_bbox`, `original_bbox`, and wrapped `bbox` paths keep precedence and behavior.

## Verification

Red-first focused command before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedNamedCropBoundaryCurrentBaseTest.php`

Result: `1 test files / 15 assertions / 2 failures`.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedNamedCropBoundaryCurrentBaseTest.php`

Result: `1 test files / 40 assertions / 0 failures`.

Focused table-geometry family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `76 test files / 2923 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-nested-named-crop-boundary-currentbase.php`

Result: exits 0 with `nested_named_crop_unwrapped=true`, `direct_table_bbox_source=table_image.bbox_left_top_width_height_fields`, `direct_table_bbox_source_coordinate_space=page_image`, `wordpress_table_rendered=true`, `stale_nested_named_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat nested `source_bbox`, nested `original_bbox`, top-level generic crop coordinate-space inheritance, explicit field-specific `table_bbox` coordinate-space, page-result table image `highres_bbox`, page-result `table_bboxes`, shared image-bbox coordinate order, duplicate span IDs, crop polygon precedence, or normalized page-image record geometry. The bounded behavior is specifically nested crop containers whose own named fields are the crop rectangle.

## Dependency Closure

No new support component is needed. The patch reuses native PHP table geometry normalization, supplied table-recognition formatting, and the existing WordPress conversion metadata path. Live OCR, Surya/Texify/Torch, tabled model execution, page-pixel visual recognition, and exact upstream model parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser or supplied-boundary behavior, especially table/equation handoff envelopes where upstream sidecars attach coordinate metadata to wrapper objects rather than each nested geometry record.
