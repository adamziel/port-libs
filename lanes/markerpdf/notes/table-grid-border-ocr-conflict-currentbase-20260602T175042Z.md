# Table Grid Border OCR Conflict Current Base

Slice: `table-grid-border-ocr-conflict-currentbase-20260602T175042Z`

Source truth:

- Upstream `sddai/markerPDF` manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table formatting through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables()` passes detected cell bboxes into `surya.ocr.run_recognition(...)` and then zips `ocr_pred.text_lines` back onto `table_cells[orig_idx]`.
- That means the detector grid is authoritative for OCR table cell order. If supplied OCR line bboxes are wider than a cell and visibly cross grid borders, the native PHP supplied boundary should preserve the upstream source-order cell assignment instead of dropping or moving text by ambiguous bbox overlap.

Current-base red probe:

- A current-base recognizer probe supplied four detector cells and four OCR lines whose bboxes spanned both cells in their row.
- Before this slice, bbox-only assignment found no single cell above the overlap threshold and the row-wide OCR lines could be dropped before Markdown table formatting.

Implementation:

- `TableRecognizer::applyOcrText()` now detects bbox-bearing OCR lines that overlap multiple detector cells.
- When those border-conflict OCR lines have the same count as detector cells, it uses upstream-equivalent source-order assignment and records `ocr_grid_border_conflicts` metadata with candidate cell indexes, overlaps, the chosen cell index, and assignment mode `source_order_grid_border`.
- `SuppliedDocumentConverter` exposes the review data as `metadata.table_ocr_grid_border_conflicts`.
- `examples/wordpress-table-grid-border-ocr-conflict-currentbase.php` demonstrates a WordPress table where all OCR line bboxes cross grid borders but the detector-grid text remains `Feature | Status` and `Images | Ready`.

Focused behavior:

- Direct recognizer coverage proves source-order assignment is used for four border-crossing OCR lines, `ocr_text_assignment=source_order_grid_border`, and four conflict rows are recorded.
- Supplied-document coverage proves the same behavior through forced OCR table routing, table assignment, Markdown formatting, stale pdftext table-line exclusion, and final metadata.
- The WordPress smoke reports `source_order_assignment_used=true`, `grid_border_conflict_count=4`, `preserved_detector_grid_text=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-grid-border-ocr-conflict-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 142 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 file, 245 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 2 files, 387 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-grid-border-ocr-conflict-currentbase.php` emitted the expected source-order conflict metadata, detector-grid text, stale-table exclusion, and native-only flags.
- `git diff --check -- lanes/markerpdf` passed.

Counters:

- Behavior tests move `610 -> 612 pass / 0 fail` from two new named focused tests.
- Mapped semantics move `443 -> 444 / 78`.

Dependency closure:

No new support component is needed. This slice reuses the existing native supplied-document converter, forced-OCR table routing, detector-cell normalization, table recognizer, tabled-style assignment, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted forced-OCR table routing, OCR prediction unwrapping, multiline OCR bbox-fragment folding, merged-cell geometry, table header spanning-grid review, rotated header-grid axes, or OCR rowspan/colspan continuation review. The new behavior is specifically bbox-bearing OCR line conflict handling at detector grid borders, using upstream source-order cell assignment only when the detector-cell count matches the OCR prediction count and preserving conflict review metadata for WordPress import.
