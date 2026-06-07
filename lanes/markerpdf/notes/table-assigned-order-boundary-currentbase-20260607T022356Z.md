# markerPDF table assigned order boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260607T022356Z`

Base accepted HEAD: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Source Truth

- Upstream `sddai/markerPDF` routes table recognition through tabled table extraction/formatting handoffs rather than rendering table Markdown from stale page text.
- The tabled saved-result contract documents cell records with `bbox`, `text`, `row_ids`, `col_ids`, and `order`; `order` is the display sort key for a cell within its assigned row/column before formatting.
- This no-GPU slice ports the supplied-boundary behavior only. It does not run Surya, tabled-pdf model inference, OCR, Torch, Python workers, or benchmark parity jobs.

## Change

`TableRecognizer::sortCells()` now preserves an existing normalized `order` value from supplied assigned cells and only synthesizes geometry-derived order for cells that do not carry one.

`formatRecognizedTables()` also sorts the exported `assigned_cells` handoff with the same row/column/order path used by Markdown and spanning-grid review, so WordPress metadata and rendered table text agree.

The covered boundary is a saved table with multiple `SpanTableCell` records sharing the same row/column anchor where physical bbox order disagrees with upstream `order`. The WordPress conversion now emits `Header A Header B` and `First Second` from the supplied order, not `Header B Header A` / `Second First` from left-to-right geometry.

## Red-First Evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderBoundaryCurrentBaseTest.php
```

Before the implementation, the focused test failed because geometry order replaced supplied `order`:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves supplied tabled cell order when same-anchor cell bboxes disagree
Expected: array (
  0 => 'Header A',
  1 => 'Header B',
  2 => 'First',
  3 => 'Second',
)
Actual: array (
  0 => 'Header B',
  1 => 'Header A',
  2 => 'Second',
  3 => 'First',
)
FAIL surfaces supplied cell order through WordPress table conversion
String does not contain '| Header A Header B |'
Haystack: # Assigned Order Table Review

| Header B Header A |
|-------------------|
| Second First      |

After assigned order review.

1 test files, 3 assertions, 2 failures
```

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 20 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php
```

Result: `3 test files, 153 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
```

Result: `2 test files, 1230 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-table-assigned-order-boundary-currentbase.php --self-test
```

Result: emitted `assigned_text_order:["Header A","Header B","First","Second"]`, `assigned_order_values:[0,1,0,1]`, `excluded_stale_pdftext_table_line:true`, `executes_python_or_models:false`, and `executes_external_pdf_tools:false`.

```sh
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryAssignedOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-assigned-order-boundary-currentbase.php
```

Result: all three syntax checks passed.

```sh
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: JSON validation passed and `git diff --check` passed.

## Non-overlap

This does not repeat accepted row/column band geometry ordering, arbitrary numeric row/column ID handling, assigned crop clipping, table cell crop-boundary review, page-image coordinate-space translation, hidden/visible JSON-style table constraints, OCR grid-border review, image/filter parsing, or inline image Decode handling. The bounded behavior is only preserving supplied same-anchor `SpanTableCell.order` through native table Markdown, assigned-cell metadata, and spanning-grid review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP `TableRecognizer`, `SuppliedDocumentConverter`, and current table formatting/review helpers. Live OCR, Surya/Torch/tabled model execution, Python pdftext/pypdfium, Texify, Streamlit/FastAPI model workers, GPU/model benchmark parity, and external PDF tools remain intentionally excluded by the markerPDF no-GPU scope.

## Root Harness

Not run - isolated micro-slice.
