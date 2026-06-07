# markerPDF mixed assigned table cell boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260607T095203Z`

Base accepted HEAD: `66b1be65d1939ece4bd38116ced4711b03888664`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates table extraction to tabled.
- Locked tabled-pdf 0.1.4 source under `/tmp/markerpdf-tabled-src-0.1.4` shows `tabled.extract.extract_tables()` calls `assign_rows_columns()` and stores those assigned `SpanTableCell` rows in `ExtractPageResult.cells`, while `rows_cols` retains the table-recognition grid.
- `tabled.schema.SpanTableCell` stores `row_ids` and `col_ids`; `tabled.formats.markdown.markdown_format()` consumes those assigned anchors rather than rerunning detector assignment at formatting time.

No Python, PDFium, PIL, Surya, OCR, tabled model inference, Torch, GPU, or external PDF tools were executed.

## Implementation

- `TableRecognizer::formatRecognizedTables()` now returns `assigned_source_boundary_reviews` for saved-assignment tables that mix valid assigned cells with stale or malformed sidecar cells.
- `TableRecognizer::assignedCellsFromRecognizedTable()` keeps the valid assigned `SpanTableCell` records when at least one assignment anchor is present, rejects missing row/column-anchor sidecar cells before detector reassignment, and records `table_assigned_cell_source_boundary` review metadata.
- `SuppliedDocumentConverter` surfaces that review as `metadata.table_assigned_source_boundary_reviews`.
- Added a focused test fixture with four valid assigned cells plus one stale in-crop cell carrying `row_ids => [null]`; before the fix, the native path recomputed detector assignment and leaked that stale text into Markdown.
- Added a WordPress smoke that verifies the Gutenberg heading/table/paragraph output, rejected-cell metadata, and no-model/no-external-tool flags.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryMixedAssignedCellBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL filters mixed saved table assignment cells without rerunning detector assignment
Expected: ['Feature', 'Status', 'Images', 'Ready']
Actual: ['Feature', 'Status', 'Images', 'Ready', 'Stale unassigned sidecar']
FAIL surfaces mixed assigned table cell boundary through supplied WordPress conversion
1 test files, 3 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
No syntax errors detected in lanes/markerpdf/src/SuppliedDocumentConverter.php

php -l lanes/markerpdf/tests/TableGeometryMixedAssignedCellBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryMixedAssignedCellBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-mixed-assigned-cell-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-mixed-assigned-cell-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryMixedAssignedCellBoundaryCurrentBaseTest.php
1 test files, 28 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
46 test files, 1868 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-mixed-assigned-cell-boundary-currentbase.php
exits 0 and reports detector_reassignment_blocked=true, stale_unassigned_sidecar_excluded=true, excluded_stale_pdftext_table_line=true, executes_python_or_models=false, and executes_external_pdf_tools=false.

git diff --check -- lanes/markerpdf
exits 0 with no output.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native supplied-document converter, table recognizer, upstream page-result/SpanTableCell boundary, table crop/band filters, Markdown table formatter, and WordPress smoke path. Live OCR, Surya/Texify/Torch, tabled model inference, Python/PDFium/PIL rendering, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat assigned crop filtering, active row/column band filtering, scalar row/column spans, page-result flattening, coordinate-order propagation, normalized/page-image coordinate localization, OCR conflict candidate localization, or stream/filter/parser behavior. The bounded behavior is only mixed saved assigned table cells where valid `SpanTableCell` row/column anchors must remain authoritative and stale unassigned sidecar cells must not be reintroduced by native detector reassignment.
