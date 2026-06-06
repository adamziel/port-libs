# Table Bbox Polygon Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T035012Z`
Base accepted HEAD: `e6e270a95e14f4f7d39cb5ce4b34b7a26d8a52c6`

## Source Truth

- Upstream markerPDF crops page images around table layout regions before tabled assignment and Markdown formatting.
- Upstream tabled/Surya geometry carries four-corner `Bbox`-derived coordinates; supplied PHP sidecars may serialize those points directly in the `bbox` field instead of as a four-number rectangle.
- This slice stays in the no-GPU markerPDF scope: supplied layout and table-recognition boundaries only; no Surya, tabled model, OCR, Python, external PDF tool, or model benchmark run.

## Behavior

- `LayoutAnnotator`, `TableFormatter`, and `TableRecognizer` now derive rectangular bboxes from direct four-corner point lists or flat eight-number coordinate lists stored in the `bbox` field.
- Recognized rows, columns, cells, OCR grid-border conflicts, table crop candidates, and layout table boxes all preserve source review labels such as `bbox_polygon_points` and `bbox_polygon_flat_coordinates`.
- The WordPress conversion smoke verifies stale PDF text in the table region is replaced by supplied Markdown and off-crop bbox-polygon cells are filtered before table output.

## Evidence

- Red-first before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBboxPolygonBoundaryCurrentBaseTest.php`
  - Result: 1 test file / 1 assertion / 3 failures. Failures showed direct `bbox` point lists were rejected by table geometry normalization, layout crop planning produced zero table boxes, and converter table/image counts did not match.
- Focused after source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBboxPolygonBoundaryCurrentBaseTest.php`
  - Result: 1 test file / 40 assertions / 0 failures.
- Adjacent boundary family:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*BoundaryCurrentBaseTest.php`
  - Result: 32 test files / 1304 assertions / 0 failures.
- Layout/table formatter family:
  - `php tools/run-tests.php lanes/markerpdf/tests/Layout*Test.php lanes/markerpdf/tests/TableFormatterTest.php`
  - Result: 3 test files / 123 assertions / 0 failures.
- Example smoke:
  - `php lanes/markerpdf/examples/wordpress-table-bbox-polygon-boundary-currentbase.php`
  - Result: exits 0 with `bbox_polygon_cells_translated=true`, `offcrop_bbox_polygon_cells_filtered_from_assignment=true`, `stale_pdftext_table_line_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP syntax:
  - `php -l lanes/markerpdf/src/TableRecognizer.php`
  - `php -l lanes/markerpdf/src/TableFormatter.php`
  - `php -l lanes/markerpdf/src/LayoutAnnotator.php`
  - `php -l lanes/markerpdf/tests/TableGeometryBboxPolygonBoundaryCurrentBaseTest.php`
  - `php -l lanes/markerpdf/examples/wordpress-table-bbox-polygon-boundary-currentbase.php`
  - Result: no syntax errors detected.
- Status JSON:
  - `php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`.
- Whitespace:
  - `git diff --check -- lanes/markerpdf`
  - Result: exits 0.

## Dependency Closure

No new support component is needed. This reuses existing native PHP supplied-boundary geometry parsing and conversion helpers.

## Non-Overlap

This avoids the accepted serialized `polygon`/`points`/`vertices`/`quad` alias coverage and crop-polygon coverage by targeting the unhandled direct `bbox` value shape itself. It does not touch live OCR/model execution, Streamlit/FastAPI workers, GPU paths, or exact upstream model benchmark parity.

## Next

Continue no-GPU markerPDF work on native searchable-PDF parser/converter behavior or non-overlapping supplied-boundary handoffs, especially page geometry, font/CMap text extraction, xref repair, stream filters, metadata, annotations, forms, security preflight, image/filter metadata, and equation/table review metadata.
