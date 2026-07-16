# Table PDF Page Bottom-Left Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T120307Z`
Base accepted HEAD: `5f425da1740b76fd38a51b6ce59a09edd9c388d7`

## Source Truth

Upstream markerPDF hands cropped rendered page images to tabled before table
assignment and Markdown formatting. PDF native page/user-space rectangles are
bottom-left origin with y increasing upward, while rendered page images and
tabled crops use top-left image coordinates with y increasing downward. Under
the no-GPU lane scope, this PHP port owns the supplied-boundary handoff for
already-recognized table geometry rather than live OCR or tabled model runs.

## Behavior

- `TableRecognizer` now recognizes explicit PDF page/user-space coordinate
  labels such as `pdf_page_bottom_left`, `pdf_user_space`, and `page_y_up`.
- Table crop bboxes, row/column bands, cells, OCR grid-border conflicts, and
  per-conflict candidate cell bboxes in those spaces are flipped through the
  rendered page height before being translated into the table crop.
- Localized records retain the original PDF-space `source_bbox` and a flipped
  `source_page_image_bbox` so WordPress review metadata can show both the
  incoming PDF geometry and the page-image handoff geometry.
- Coordinate-space reviews report `pdf_page_image_size`, translated row/column
  and cell counts, and translated conflict counts for this boundary.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPdfPageBottomLeftBoundaryCurrentBaseTest.php`
  - `1 test files, 50 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/TableGeometry.*CurrentBaseTest\.php$' | sort)`
  - `62 test files, 2428 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-pdf-page-bottom-left-boundary-currentbase.php`
  - exits 0 with `coordinate_review_status=translated_to_table_crop`,
    `source_coordinate_space=pdf_page_bottom_left`,
    `pdf_page_image_size={"width":612,"height":792}`,
    `translated_cell_count=6`, `translated_conflict_count=1`,
    `assigned_table_texts=["Feature","Status","Images","Ready"]`,
    `stale_pdftext_line_excluded=true`,
    `offcrop_pdf_cells_excluded=true`,
    `executes_python_or_models=false`, and
    `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted table crop aliasing, page-result image-bbox
normalization, coordinate-order propagation, assigned-cell crop filtering,
OCR grid conflict crop-localization, or normalized page-image/table-crop
geometry. The bounded behavior is only PDF bottom-left page/user-space table
geometry supplied at the table-recognition boundary.

## Dependency Closure

No new support component is needed. The patch reuses native PHP supplied
document conversion, table recognition localization, assignment filtering,
grid review, and Markdown formatting. Live OCR, Surya/Texify/Torch/tabled
model execution, PDFium rendering, multiprocessing, external PDF tools, and
exact upstream model benchmark parity remain intentionally out of scope under
the current no-GPU markerPDF directive.
