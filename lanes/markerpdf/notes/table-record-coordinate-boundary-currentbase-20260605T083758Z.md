# markerpdf table record-coordinate boundary current-base 2026-06-05

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T083758Z`

Base accepted HEAD: `a7ccc526ab84e347dfce56c71246c1121d0af914`

## Source truth

- Upstream markerPDF crops rendered page images before `tabled.assignment.assign_rows_columns`, so table recognition geometry must be table-crop local before row/column assignment and Markdown formatting.
- Serialized no-GPU handoffs can carry `coordinate_space`, `bbox_coordinate_space`, or `geometry_space` on individual row, column, cell, and OCR grid-border conflict records rather than on the whole table result.
- This slice stays inside native supplied-boundary behavior: no Surya, tabled model inference, OCR, Python, GPU, pypdfium, or external PDF tool execution.

## Implementation

- `TableRecognizer::localizeRecognizedTableGeometry()` now reads per-record coordinate-space metadata for rows, columns, cells, and OCR conflict rows.
- Page-image records are translated through the table crop bbox, normalized records are scaled from the 1000-unit table space, and table-crop-local records are preserved.
- Source bbox and source coordinate-space metadata are retained for WordPress review, and coordinate reviews now include `source_record_coordinate_spaces` counts.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRecordCoordinateBoundaryCurrentBaseTest.php`
  - `1 test files, 46 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'TableGeometry|TableRecognizerTest|TableFormatterTest|TableUtilsTest' | sort)`
  - `15 test files, 987 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-record-coordinate-boundary-currentbase.php`
  - emitted `record_geometry_translated=true`, `offcrop_page_image_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `translated_cell_count=6`, `translated_conflict_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The slice reuses existing native table geometry, supplied-document conversion, and Markdown formatting code. Remaining GPU/model table recognition parity stays intentionally out of scope under the current markerPDF no-GPU directive.

## Next task

Continue with a non-overlapping native markerPDF slice around searchable-PDF text extraction, fonts/CMaps, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, or another supplied-boundary table/equation handoff.
