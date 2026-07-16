# Table OCR Source Bbox Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T163414Z`
Base: `989d72297d7b2e126aa296fdd7e44238e330f68d`

## Source Truth

Upstream markerPDF table conversion receives table recognition and OCR
TextLine geometry from Surya/tabled objects. In the no-GPU PHP port, those
objects are supplied as serialized arrays. Accepted table/cell geometry already
accepts saved review-sidecar source rectangles such as `source_bbox`, but OCR
TextLine assignment only recognized `polygon`, primary `bbox`, or named fields.

This slice keeps the native supplied-boundary contract aligned with that source
truth: if a supplied OCR TextLine carries only source geometry, the recognizer
uses it as a fallback bbox for OCR/cell assignment and grid-border review while
preserving polygon-before-bbox precedence.

## Behavior

- `TableRecognizer::ocrLineBbox()` now falls back through wrapped geometry and
  source geometry aliases after the existing polygon, primary bbox, and named
  field paths.
- OCR grid-border conflict rows copy `source_bbox` /
  `source_page_image_bbox`, `source_coordinate_space`, inferred
  `source_coordinate_source`, and endpoint-order review metadata from supplied
  OCR TextLine records.
- Source-bbox-only OCR lines that span detector cell borders now produce the
  same `source_order_grid_border` conflict review metadata as primary-bbox OCR
  lines and replace stale searchable-PDF table text in the supplied WordPress
  conversion path.

## Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryOcrSourceBboxBoundaryCurrentBaseTest.php`

Failed with no `source_order_grid_border` direct assignment and zero converter
grid-border conflicts.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryOcrSourceBboxBoundaryCurrentBaseTest.php`

Passed with `1 test files, 30 assertions, 0 failures`, adding 2 focused PASS
cases.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-ocr-source-bbox-boundary-currentbase.php`

Exits 0 and reports `grid_border_conflict_count=4`,
`source_order_assignment_used=true`, `source_bbox_geometry_used=true`,
`preserved_detector_grid_text=true`, `excluded_stale_pdftext_table_line=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP table
geometry normalization and source-geometry review helpers. Live OCR,
Surya/tabled/Torch model execution, raster table detection, and upstream model
benchmark parity remain intentionally out of scope under the current no-GPU
markerPDF directive.
