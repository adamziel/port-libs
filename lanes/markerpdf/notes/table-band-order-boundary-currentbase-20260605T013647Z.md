# markerPDF table band-order boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T013647Z`

Base accepted HEAD: `5c1e831a4cd16b50e19b19a5942fd02353b5a990`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table layout boxes through `marker/tables/table.py::get_table_boxes()` before recognition and Markdown formatting.
- The locked tabled-pdf 0.1.4 assignment boundary treats row and column identifiers as model identifiers. Physical row and column order follows the cropped table geometry, so arbitrary `row_id` and `col_id` values must not be sorted numerically before Markdown, span-grid review, grid-border review, or header-reference generation.

## Change

`TableRecognizer` now carries row and column band order metadata from supplied geometry after assignment, then uses that order for:

- Markdown table formatting;
- merged-cell geometry and row/column span handling;
- spanning-grid review rows, columns, continuation cells, and accessibility headers;
- grid-border review candidates and assigned render-cell metadata.

The public row and column IDs remain unchanged. A supplied table with rows `[20, -5]` and columns `[100, -10]` now renders in physical top-to-bottom and left-to-right order instead of numeric ID order, while WordPress conversion still replaces stale pdftext table lines with supplied table Markdown.

## Red-First Evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php
```

Before the implementation, the focused test failed because numeric ID sorting inverted both axes:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves geometric row and column band order when supplied ids are arbitrary
Expected: '| Feature | Status |
|---------|--------|
| Images  | Ready  |'
Actual: '| Ready  | Images  |
|--------|---------|
| Status | Feature |'
FAIL surfaces geometry-ordered arbitrary band ids through supplied WordPress conversion
String does not contain '| Feature | Status |'
Haystack: # Band Order Table Review

| Ready  | Images  |
|--------|---------|
| Status | Feature |

After band order review.

1 test files, 5 assertions, 2 failures
```

## Verification

```sh
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-band-order-boundary-currentbase.php
```

All three syntax checks passed.

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 28 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
```

Result: `7 test files, 1187 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-table-band-order-boundary-currentbase.php
```

Result: emitted `geometry_ordered_rows:[20,-5]`, `geometry_ordered_cols:[100,-10]`, `header_texts:["Feature","Status"]`, `data_texts:["Images","Ready"]`, `excluded_stale_pdftext_table_line:true`, `executes_python_or_models:false`, and `executes_external_pdf_tools:false`.

```sh
git diff --check -- lanes/markerpdf
```

Result: passed.

## Non-overlap

This does not repeat accepted table geometry assignment crop clipping, table cell crop-boundary review, grid-border crop-boundary metadata, named/numeric/reversed bbox normalization, OCR polygon text assignment, supplied OCR prediction unwrapping, merged-cell geometry, rotated header grids, or page-image coordinate-space translation. The bounded behavior is only preserving geometry-derived row and column band order when supplied IDs are arbitrary.

## Dependency Closure

No new support component is needed. This reuses the native `TableRecognizer`, `TableFormatter`, and `SuppliedDocumentConverter` supplied-boundary pipeline. Live OCR, Surya/Torch model execution, tabled-pdf model inference, Texify, Python pdftext/pypdfium execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Root Harness

Not run - isolated micro-slice.
