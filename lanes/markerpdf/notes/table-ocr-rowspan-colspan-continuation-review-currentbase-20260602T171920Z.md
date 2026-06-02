# Table OCR Rowspan Colspan Continuation Review Current Base

Slice: `table-ocr-rowspan-colspan-continuation-review-currentbase-20260602T171920Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized table formatting through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables()` assigns OCR text to detected table cells before table recognition and row/column assignment.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::assign_rows_columns()` preserves span occupancy on `SpanTableCell.row_ids` and `SpanTableCell.col_ids`.
- Locked `tabled-pdf==0.1.4` `tabled/formats/markdown.py` and `html.py` consume first row/column anchors when formatting with `tabulate(...)`, so WordPress import needs native review metadata before Markdown/HTML drops covered rowspan/colspan occupancy.

Current-base red probe:

- A current-base probe with a full-width OCR header cell plus a second OCR continuation cell anchored in the header's covered grid slot produced two render cells in `TableRecognizer::spanningGridReview()`.
- The grid anchor for covered columns could point at the continuation fragment or leave the continuation as a separate header, so WordPress span rendering could emit a `colspan` header without its continuation text or duplicate an occupied grid slot.

Implementation:

- `TableRecognizer::spanningGridReview()` now groups assigned cells that share the same first row/column anchor or whose anchor lands inside an already-covered rowspan/colspan grid slot.
- Grouped render cells expose `source_cell_count`, `text_parts`, `anchor_cell_bbox`, `continuation_count`, and `continuation_cells` while preserving the existing `rowspan`, `colspan`, `scope`, `header_role`, `grid_cells`, `grid_bbox`, `rotated`, and covered-cell metadata.
- `examples/wordpress-table-ocr-rowspan-colspan-continuation-review-currentbase.php` runs the supplied-document forced-OCR table path and renders the review grid into WordPress table HTML with one `scope="colgroup" colspan="3"` header, one `scope="rowgroup" rowspan="2"` header, and skipped covered cells.

Focused behavior:

- Direct recognizer coverage proves a continuation cell anchored in a full-width header's covered columns is folded into the covering `Inventory continued` header while the continuation source bbox remains visible in review metadata.
- Supplied-document coverage proves the same behavior through forced OCR detector cells, zipped OCR text lines, row/column assignment, `table_spanning_grid_review`, stale pdftext table-line exclusion, and final Markdown conversion.
- The WordPress smoke reports `joined_anchor_continuation=true`, `continuation_count=1`, `has_th_colgroup_colspan_3=true`, `has_th_rowgroup_rowspan_2=true`, `covered_cells_skipped=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-rowspan-colspan-continuation-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 2 files, 363 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-rowspan-colspan-continuation-review-currentbase.php` emitted the expected continuation, scoped-header, covered-cell, stale-table-exclusion, and native-only flags.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table formatter, table recognizer, forced-OCR cell routing, tabled-style row/column assignment, and WordPress table smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted merged-cell geometry, discontiguous-span suppression, horizontal/rotated spanning-header grid review, multiline OCR bbox-fragment folding, or forced-OCR table prediction unwrapping. The new behavior is specifically review-time continuation folding for OCR table cells whose anchors land inside already occupied rowspan/colspan grid slots before WordPress table rendering.
