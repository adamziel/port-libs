# markerPDF table assigned order review boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T034218Z`

Base accepted HEAD: `e0a13ef9a780753d5899fbbc435cefb0324e5b29`

## Source Truth

- Upstream `sddai/markerPDF` delegates detected table formatting to tabled-style saved table records rather than rebuilding WordPress table output from stale page text.
- The tabled saved-result contract carries `SpanTableCell.order` beside `bbox`, `text`, `row_ids`, and `col_ids`; that order is the source display order for same-anchor fragments before Markdown/table review.
- This no-GPU slice ports the supplied-boundary metadata behavior only. It does not run Surya, tabled-pdf model inference, OCR, Torch, Python workers, rasterizers, or benchmark parity jobs.

## Change

`TableRecognizer::spanningGridReview()` now exposes supplied cell order in review metadata for grouped same-anchor table fragments:

- `source_orders` on render cells and anchor grid cells;
- `anchor_cell_order` on render cells and anchor grid cells;
- `order` on `continuation_cells` entries.

The existing Markdown sort path remains unchanged. The new behavior lets WordPress review tooling explain why grouped span fragments render as `Header A Header B` even when the source bboxes or source array order would otherwise suggest the opposite.

## Red-First Evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderReviewBoundaryCurrentBaseTest.php
```

Before the implementation, the focused test failed because the spanning-grid review omitted source-order metadata:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL exposes supplied source order on spanning-grid grouped review cells
Expected: array (0 => 0, 1 => 1)
Actual: NULL
FAIL surfaces assigned source order review metadata through WordPress conversion
Expected: array (0 => 0, 1 => 1)
Actual: NULL

1 test files, 10 assertions, 2 failures
```

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderReviewBoundaryCurrentBaseTest.php
```

Result: `1 test files, 19 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedOrderReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryScalarSpanBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
```

Result: `4 test files, 144 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-table-assigned-order-boundary-currentbase.php --self-test
```

Result: emitted `render_source_orders:[[0,1],[0,1]]`, `render_anchor_cell_orders:[0,0]`, `continuation_order_values:[[1],[1]]`, `assigned_text_order:["Header A","Header B","First","Second"]`, `excluded_stale_pdftext_table_line:true`, `executes_python_or_models:false`, and `executes_external_pdf_tools:false`.

```sh
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryAssignedOrderReviewBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-assigned-order-boundary-currentbase.php
```

Result: all three syntax checks passed.

```sh
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
```

Result: `lane-status json ok`.

```sh
git diff --check -- lanes/markerpdf
```

Result: passed with no whitespace errors.

## Status Delta

- `phpPass`: `2926 -> 2928` for the two new focused table assigned-order review cases.
- `wordpressScenarios`: unchanged at `2437`; this patch updates the existing assigned-order WordPress smoke rather than adding a new example file.
- `lane-status.json` now identifies this accepted-base table-review slice as the latest focused work.

## Non-overlap

This does not repeat accepted table Markdown ordering, row/column band order, page-result coordinate order, saved table row/column default coordinate order, mixed assigned-cell filtering, table source-bbox localization, OCR grid-border review, native pdftext malformed-envelope behavior, image/filter metadata, or live model/OCR execution. The bounded behavior is only review metadata for supplied `SpanTableCell.order` on grouped same-anchor fragments.

## Next Task

Continue with a non-overlapping native no-GPU markerPDF slice around searchable-PDF fonts/CMaps, stream filters, xref repair, annotations/forms/security preflight, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `TableRecognizer`, `SuppliedDocumentConverter`, and existing supplied-boundary table review helpers. Live OCR, Surya/Torch/tabled model execution, Python pdftext/pypdfium, Texify, Streamlit/FastAPI model workers, GPU/model benchmark parity, and external PDF tools remain intentionally excluded by the current markerPDF no-GPU scope.

## Root Harness

Not run - isolated micro-slice.
