# Table OCR Polygon Stale Bbox Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260604T232629Z`
Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes forced table OCR through `marker/tables/table.py` into locked `tabled-pdf==0.1.4`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables()` receives Surya OCR predictions, iterates `ocr_pred.text_lines`, and uses `ocr_line.text` for table cell text.
- Surya `0.6.10` `surya/schema.py::TextLine` extends `PolygonBox`; its `bbox` is a computed field derived from `polygon`, and `PolygonBox.bbox` normalizes x/y ordering before geometry consumers use it.
- Therefore native PHP supplied OCR `TextLine` dictionaries that include both a serialized `polygon` and a stale or transported `bbox` must use the polygon-derived bbox for table text assignment.

## Implementation

- `TableRecognizer::ocrLineBbox()` now derives OCR TextLine geometry from `polygon` before falling back to serialized `bbox` or named bbox fields.
- Table row/column/cell records still keep the existing Bbox behavior; this patch only changes OCR TextLine geometry, which is the Surya PolygonBox-derived boundary.
- Added a recognizer test proving stale bboxes no longer swap `Feature`/`Status` or `Images`/`Ready`.
- Added a supplied-document WordPress path and smoke example proving stale pdftext table lines are replaced and the final Gutenberg table uses polygon geometry.

## Verification

Red before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php
FAIL prefers serialized OCR TextLine polygon over stale bbox geometry
Expected: ['Feature', 'Status', 'Images', 'Ready']
Actual: ['Status', 'Feature', 'Ready', 'Images']
1 test files, 386 assertions, 1 failures
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 941 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-table-ocr-polygon-stale-bbox-boundary-currentbase.php
```

The smoke emitted `polygon_assignment_preserved=true`, `stale_bbox_assignment_excluded=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, `SuppliedDocumentConverter`, table crop planning, and supplied OCR boundary. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF direction.

## Non-Overlap

This does not repeat accepted crop-boundary clipping, named-bbox normalization, numeric-string coercion, forced-OCR routing, OCR prediction unwrapping, row/col span review, grid-border conflict handling, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically serialized Surya OCR TextLine `polygon` precedence over stale `bbox` values before table-cell text assignment and WordPress table rendering.
