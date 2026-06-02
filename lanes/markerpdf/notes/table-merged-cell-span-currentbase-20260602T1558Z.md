# Table Merged-Cell Span Current Base

Source truth: upstream `sddai/markerPDF` delegates table assignment to locked `tabled-pdf==0.1.4`. In `/tmp/markerpdf-tabled-src/tabled/assignment.py`, `handle_rowcol_spans()` walks row and column bands and stops expanding once a started span reaches a non-intersecting or occupied band. `SpanTableCell` in `/tmp/markerpdf-tabled-src/tabled/schema.py` keeps `row_ids` and `col_ids`, and `tabled/formats/markdown.py` formats only the first assigned row/column while the assigned cell object preserves span metadata for review/export.

This slice fixes the native `TableRecognizer::handleRowColSpans()` boundary so merged-cell spans stay contiguous. A cell can still span bands before and after its anchor when every intervening band is covered, preserving the accepted full-width header and row-spanning side-label behavior. It no longer jumps over an unspanned middle band to append a later open column or row, preventing WordPress table import code from emitting unsafe `colspan` / `rowspan` attributes for discontiguous geometry.

Focused behavior:

- Red-first focused test showed `Section note` was assigned `col_ids=[0,2]` before the fix, crossing a narrow unspanned middle column.
- After the fix, the same supplied table assigns `Section note` to `col_ids=[0]`, exports no merged-cell geometry, and leaves the middle/right cells separate for WordPress review.
- Existing merged-header behavior still reports `Inventory summary` as `col_ids=[0,1,2]`, and the row-spanning side label remains `row_ids=[1,2]`.
- `examples/wordpress-table-merged-cell-span-currentbase.php` emits a core table block with a blank middle cell instead of `colspan`, and reports `discontiguous_span_suppressed=true`, `section_note_col_ids=[0]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` failed with `Section note` actual `col_ids=[0,2]`.
- After fix: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file / 60 assertions / 0 failures.
- Adjacent table gate: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/TableUtilsTest.php` passed with 4 files / 255 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-merged-cell-span-currentbase.php` emitted `discontiguous_span_suppressed=true`, `merged_cell_geometry=[]`, and no Python/model/external-tool execution.
- Syntax checks passed for `TableRecognizer.php`, `TableRecognizerTest.php`, and `wordpress-table-merged-cell-span-currentbase.php`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure: no new support component is needed. This reuses the existing native supplied table-recognition boundary, row/column assignment, merged-cell geometry exporter, and WordPress table smoke path. Full upstream runner parity remains dependency-gated on Poetry plus `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.
