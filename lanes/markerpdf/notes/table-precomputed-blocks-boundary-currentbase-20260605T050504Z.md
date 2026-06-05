# Table Precomputed Blocks Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T045848Z`

Base accepted HEAD: `790f14bb8a62977a43839ba78bb37c3251b8547b`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table text cells through `marker/tables/table.py::get_cells()`.
- Pinned `surya-ocr==0.6.13` `surya/input/pdflines.py::get_table_blocks()` returns coordinates relative to the input table crop after filtering pdftext lines by table intersection.
- Pinned `tabled-pdf==0.1.4` `tabled.assignment.assign_rows_columns()` consumes those crop-local cells before row/column assignment and Markdown formatting.

## Change

- `TableRecognizer::tableBlocksFromTextLine()` now treats explicitly marked `table_blocks_coordinate_space=table_crop` payloads as already crop-local precomputed `get_table_blocks()` output.
- Legacy unmarked `table_blocks` and `blocks` payloads keep the existing full-page filtering path, preserving prior focused coverage.
- Precomputed crop-local cells retain upstream bboxes, including crop-crossing negative coordinates, while `table_text_cell_boundary_reviews` exposes clipped WordPress overlay metadata.
- Added a WordPress smoke for precomputed table blocks so stale pdftext table lines are replaced without detector OCR, Python models, or external PDF tools.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPrecomputedBlocksBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL accepts precomputed get_table_blocks cells as table crop local geometry
Missing supplied detector cells for table index 0.
FAIL surfaces precomputed crop-local table blocks through supplied WordPress conversion
Missing supplied detector cells for table index 0.

1 test files, 0 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPrecomputedBlocksBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS accepts precomputed get_table_blocks cells as table crop local geometry
PASS surfaces precomputed crop-local table blocks through supplied WordPress conversion

1 test files, 27 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPrecomputedBlocksBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 461 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-precomputed-blocks-boundary-currentbase.php
```

The smoke emitted `table_text_cell_source=precomputed_get_table_blocks`, `table_needs_ocr=[false]`, `table_cell_counts=[2]`, `precomputed_blocks_used_without_detector_ocr=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted crop-local row/column band clipping, assigned cell clipping, normalized/page-image/tabled-result bbox localization, OCR polygon geometry, OCR grid-border conflict review, forced-OCR routing, multiline OCR headers, span/rowspan/colspan review, or table layout bbox normalization. The bounded behavior is specifically serialized precomputed `get_table_blocks()` cells whose coordinates are already relative to the table crop.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, `SuppliedDocumentConverter`, table crop planning, existing table text-cell boundary review, and WordPress smoke path. Live OCR, Surya/Torch model execution, PDFium rendering, tabled model inference, Texify equation recognition, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
