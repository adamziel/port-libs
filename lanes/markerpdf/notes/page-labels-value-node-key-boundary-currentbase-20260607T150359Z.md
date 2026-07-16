# MarkerPDF PageLabels value/node key boundary current-base

- Slice: `markerpdf-page-labels-boundary-current-base-20260607T150359Z`
- Base accepted HEAD: `180cbd9396d0f069d253898f9c8b943402e9e222`
- Scope: native no-GPU PageLabels number-tree parsing for searchable PDFs and WordPress page-break metadata.

## Source truth

PDF page label dictionaries under a PageLabels number tree are values in a `/Nums` array. A dictionary that exposes number-tree node keys (`/Nums`, `/Kids`, or `/Limits`) at its own top level is node-shaped, not a clean page-label dictionary value. Existing accepted behavior already treats PageLabels node keys and label dictionary keys as top-level dictionary entries and already preserves mixed root/intermediate nodes that contain direct `/Nums` plus `/Kids`. This slice keeps that node traversal intact and only rejects node-shaped dictionaries when they appear in a `/Nums` value slot.

This is bounded to parser behavior. No OCR, Surya/Texify/Torch, model execution, GPU work, or external PDF tools were used.

## Implementation

- `PdfTextExtractor` now rejects `/Nums` value dictionaries that contain top-level `/Nums`, `/Kids`, or `/Limits` before they can claim a page index.
- `MarkerAppPreview` fallback PageLabels parsing mirrors the same value-boundary guard.
- The accepted mixed `/Nums` + `/Kids` PageLabels node behavior is intentionally unchanged because the new guard is only applied after a `/Nums` value has already been resolved as a prospective label dictionary.

## Red-first evidence

Before the parser change, the new focused test failed because node-shaped value dictionaries claimed page labels before later valid duplicate entries:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsValueNodeKeyBoundaryCurrentBaseTest.php
FAIL rejects PageLabels Nums value dictionaries that are number-tree nodes
Expected: ['Cover-', 'Body 4', 'App-Z']
Actual:   ['node-stale-77', 'Body 4', 'node-app-Z']
1 test files, 1 assertions, 1 failures
```

After the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsValueNodeKeyBoundaryCurrentBaseTest.php
PASS rejects PageLabels Nums value dictionaries that are number-tree nodes
1 test files, 10 assertions, 0 failures
```

Adjacent PageLabels current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
27 test files, 572 assertions, 0 failures
```

Preview fallback coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
1 test files, 110 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsValueNodeKeyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-value-node-key-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

## WordPress smoke

Added `examples/wordpress-pdf-page-labels-value-node-key-currentbase.php`. The smoke emits native WordPress page-break blocks with labels `Cover-`, `Body 4`, and `App-Z` and asserts stale node-value labels such as `node-stale-77`, `node-app-Z`, `nested-stale-99`, and `nested-kid-55` are excluded.

## Status delta

- Focused PHP PASS files: `+1` (`2897 -> 2898`)
- WordPress-relevant scenarios: `+1` (`2418 -> 2419`)
- Mapped upstream denominator: unchanged; this is an additive boundary case inside the already mapped PageLabels parser cluster.

## Non-overlap

This avoids accepted PageLabels clusters for mixed `/Nums` + `/Kids`, duplicate `/Nums` keys, malformed scalar values, malformed value ordering, top-level nested private keys, kid limit ordering, and direct kid dictionaries. The new coverage is specifically `/Nums` value dictionaries that expose number-tree node keys and carry stale label-key decoys.

## Dependency closure

No new support component is needed. The implementation reuses the existing native PDF object resolver, balanced dictionary reader, and top-level PageLabels value scanners.
