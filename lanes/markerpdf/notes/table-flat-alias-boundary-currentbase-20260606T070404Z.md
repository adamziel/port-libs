# markerPDF flat table alias geometry boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T070404Z`
Base accepted HEAD: `14edf96a09146b5955b63623b601e9398fd5b965`

## Source Truth

- Upstream marker documents table extraction through `TableConverter` and JSON output with cell bounding boxes: https://github.com/datalab-to/marker/blob/master/README.md#extract-tables
- The tabled JSON contract documents per-table `cells`, `row_ids`, `col_ids`, `rows`, `cols`, `image_bbox`, and table `bbox`, and notes that table bboxes are relative to the image bbox: https://github.com/VikParuchuri/tabled/blob/master/README.md
- The upstream assignment boundary consumes `detection_result.rows`, `detection_result.cols`, and `detection_result.cells` when assigning cells and span IDs: https://github.com/VikParuchuri/tabled/blob/master/tabled/assignment.py

## Implementation

- `TableRecognizer` now canonicalizes flat saved-recognition aliases before assignment:
  - `row_bboxes`, `row_boxes`, and `row_bounds` become canonical `rows`.
  - `columns`, `column_bboxes`, `col_bboxes`, `column_boxes`, and `col_boxes` become canonical `cols`.
- Alias-specific coordinate-space keys such as `row_bboxes_coordinate_space` and `columns_coordinate_space` now participate in the existing page-image to table-crop localization boundary.
- When localization changes canonical rows/cols, the original alias arrays are synchronized so returned recognition metadata does not carry stale page-image coordinates beside table-crop coordinate-space flags.
- No live OCR/model path was introduced. The patch stays on supplied recognition handoff data.

## Red-First Evidence

Before the source edit, a flat recognition table containing `row_bboxes`, `columns`, and three cells produced a plausible Markdown table but lost the upstream geometry contract:

- `recognized_tables[0]` lacked canonical `rows` and `cols`.
- Header assignment fell back to heuristic `row_ids=[0]`, `col_ids=[0]` instead of the supplied row/column ids.

After the source edit, the same probe preserves `row_ids=[10]`, `col_ids=[20,21]` for the spanning header and returns canonical `rows`/`cols`.

## Focused Evidence

- `php -l lanes/markerpdf/src/TableRecognizer.php` => no syntax errors
- `php -l lanes/markerpdf/tests/TableGeometryFlatAliasBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-table-flat-alias-boundary-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryFlatAliasBoundaryCurrentBaseTest.php` => `1 test files, 39 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*BoundaryCurrentBaseTest.php` => `35 test files, 1422 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => `2 test files, 1213 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-flat-alias-boundary-currentbase.php` emitted `coordinate_status=translated_to_table_crop`, `assigned_header_col_ids=[20,21]`, `assigned_header_colspan=2`, `active_col_ids=[20,21]`, `flat_alias_geometry_preserved=true`, `stale_pdftext_table_line_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` => no whitespace errors.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table crop planner, table recognizer, tabled-style row/column assignment, span-grid review, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, tabled model inference, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted table-local crop clipping, page-image geometry translation, normalized page-image geometry, page-result flattening, named/numeric/reversed/wrapped bbox normalization, source-bbox fallback, source field-shape metadata review, OCR conflict localization, assigned-band filtering, or span-grid rendering. The new behavior is specifically flat saved tabled aliases (`row_bboxes`/`columns` and related variants) being canonicalized before crop-local assignment and WordPress table formatting.

## Next Task

Continue markerPDF no-GPU work with non-overlapping native searchable-PDF parser or supplied-boundary behavior: fonts/CMaps, stream filters, xref repair, metadata/outlines/annotations/forms, page geometry, image/filter metadata, or distinct table/equation handoff gaps.
