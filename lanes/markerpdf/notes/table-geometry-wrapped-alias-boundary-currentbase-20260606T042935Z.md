# markerPDF Table Geometry Wrapped Alias Boundary Current Base

Date: 2026-06-06 04:29 UTC

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T042935Z`

## Behavior

`TableRecognizer` now accepts record-level wrapped geometry aliases for supplied table crops, rows, columns, cells, and OCR grid-border conflict rows before table-crop localization.

- Generic wrapper keys `box`, `rect`, `rectangle`, `bounds`, and `bounding_box` are normalized through the same bbox parser as `bbox`.
- The parser records source labels such as `box.bbox_array`, `rect.bbox_left_top_right_bottom_fields`, `bounds.bbox_xy_width_height_fields`, and `bounding_box.bbox_center_width_height_fields`.
- Raw endpoint normalization review now uses the same wrapper aliases.
- Nested crop records can use the same wrapper aliases when resolving a saved table crop boundary.
- WordPress table conversion still keeps the supplied layout crop authoritative while preserving the alias source metadata in table grid review rows.

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes supplied table recognition through tabled/Surya-style sidecar objects before Markdown conversion. The locked lane manifest already maps supplied table-recognition sidecars, crop localization, assigned row/column ids, and WordPress table review metadata as native PHP boundaries.

The bounded dependency behavior here is JSON/Pydantic-style geometry wrapper normalization: saved table sidecars may carry the same rectangle as a top-level `box`, `rect`, `bounds`, `rectangle`, or `bounding_box` record instead of only as a bare `bbox`. The native converter must normalize those shapes before filtering stale off-crop rows/cells and before emitting WordPress grid review metadata.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 0 assertions, 2 failures`. Both cases failed with `Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.`

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 54 assertions, 0 failures`.

Adjacent table-geometry family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'TableGeometry.*BoundaryCurrentBaseTest\\.php$' | sort)`

Result: `33 test files, 1358 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-wrapped-alias-boundary-currentbase.php`

Result: emitted `source_geometry_aliases=["box","rect","bounds","rectangle","bounding_box"]`, `supplied_boundaries=["layout","table-recognition","table-formatting"]`, `recognized_table_status="translated_to_table_crop"`, `render_cell_sources=["box.bbox_array","rect.bbox_left_top_right_bottom_fields","bounds.bbox_xy_width_height_fields","bounding_box.bbox_center_width_height_fields"]`, `stale_pdftext_and_alias_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/TableRecognizer.php`
- `php -l lanes/markerpdf/tests/TableGeometryWrappedAliasBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-table-wrapped-alias-boundary-currentbase.php`

Result: no syntax errors.

Diff hygiene:

`git diff --check -- lanes/markerpdf`

Result: passed.

## Status Delta

- Behavior tests move `2059 -> 2061`.
- `phpPass` moves `2389 -> 2391`.
- WordPress scenarios move `2042 -> 2043`.
- Direct table-geometry alias test adds `54` passing assertions after the red-first failure.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied-table recognizer, bbox parser, crop-localization review, assigned-cell filtering, table grid review, and WordPress conversion smoke path. GPU/model/OCR execution, Surya/Torch, Texify, pypdfium/PDFium raster rendering, tabled-pdf runtime execution, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted wrapped `bbox` payload parsing, polygon aliases, serialized polygons, nested crop bboxes, crop polygon precedence, normalized table coordinates, normalized page-image coordinates, center/extent aliases, endpoint aliases, source-bbox fallback, detector-source review metadata, assigned-band clipping, or table cell crop-boundary filtering. The bounded behavior is specifically record-level geometry wrapper aliases outside the `bbox` field before current-base table crop localization and WordPress grid review.

## Next Task

Continue with non-overlapping native markerPDF behavior around searchable-PDF parser fidelity, fonts/CMaps, stream filters, xref repair, page geometry, annotations/forms/security metadata, image/filter review, or supplied-boundary table/equation handoffs without Python/model/external PDF tools.
