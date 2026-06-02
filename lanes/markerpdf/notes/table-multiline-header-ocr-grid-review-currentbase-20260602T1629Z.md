# Table Multiline Header OCR Grid Review Current Base

Slice: `table-multiline-header-ocr-grid-review-currentbase-20260602T1629Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized tables through `marker/tables/table.py::format_tables()` and the locked `tabled-pdf==0.1.4` dependency.
- `/tmp/markerpdf-tabled-src/tabled/inference/recognition.py::recognize_tables()` applies OCR text to detector cells before table recognition and assignment.
- `/tmp/markerpdf-tabled-src/tabled/assignment.py::assign_rows_columns()` assigns `SpanTableCell.row_ids` and `col_ids`, merges multiline rows, and expands row/column spans.
- `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py::markdown_format()` and `html.py::html_format()` call `tabulate(..., headers="firstrow")` while consuming each cell anchor's first row/column, so WordPress import needs the existing review grid to preserve header spans and covered cells.

Current-base red probe:

- An inline current-base probe supplied seven OCR fragments for six detector cells.
- Before the patch, `TableRecognizer::applyOcrText()` zipped by list index and emitted shifted cell text: `Inventory | OCR summary | Media group | Image count | 12 | Review state`.
- That lost the second header fragment, moved `OCR summary` into the row-header detector cell, and dropped the tail `Needs review` cell before WordPress grid review.

Implementation:

- `TableRecognizer::applyOcrText()` now detects OCR prediction items that all carry `bbox` values.
- Bbox-bearing OCR fragments are assigned to the detector cell whose bbox contains or substantially overlaps the OCR fragment, then fragments inside the same detector cell are sorted in reading order and joined with spaces.
- Existing list-index behavior remains unchanged for ordinary upstream-shaped OCR predictions without bboxes.
- `examples/wordpress-table-multiline-header-ocr-grid-review-currentbase.php` renders the resulting `table_spanning_grid_review` into a WordPress table preview with a joined multiline header.

Focused behavior:

- Direct recognizer coverage proves `Inventory` plus `OCR summary` become one `Inventory OCR summary` full-width top-row header while `Needs review` remains in the bottom-right cell.
- Supplied-document coverage proves the same behavior through forced OCR table routing, table assignment, `table_spanning_grid_review`, stale pdftext table-line exclusion, and final Markdown conversion.
- The WordPress smoke emits `<th scope="colgroup" colspan="3">Inventory OCR summary</th>` and `<th scope="rowgroup" rowspan="2">Media group</th>` while covered grid cells are skipped.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-multiline-header-ocr-grid-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 91 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 file, 206 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-multiline-header-ocr-grid-review-currentbase.php` emitted `joined_multiline_header_fragments=true`, `has_th_colgroup_colspan_3=true`, `has_th_rowgroup_rowspan_2=true`, `preserved_tail_cell_text=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

No new support component is needed. This slice reuses the existing native supplied-document converter, forced-OCR table routing, table recognizer, row/column assignment, spanning-grid metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted forced-OCR table routing, upstream OCR prediction unwrapping, multiline row merge, merged-cell geometry, discontiguous span suppression, or table header spanning-grid review. The new behavior is specifically bbox-aware OCR fragment folding before assignment so multiline OCR headers stay attached to their detector cell and existing WordPress grid review receives correct header text and tail cells.
