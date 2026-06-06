# markerpdf pdftext dictionary layout/order image-bbox boundary current base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T142614Z`
Accepted base: `064eddcbafd853b7c3b205d0660f5ea55fe616f8`

## Source truth

Upstream markerPDF obtains selected pdftext dictionary pages, renders the same
selected pages for layout/order models, then maps supplied normalized model
geometry through the page image extent before annotation and reading-order
sorting. Native supplied-boundary imports therefore need a positive
`image_bbox` extent before trusting normalized `[0..1]` layout/order rows.

No live Surya, OCR, Texify, Torch, pdftext, pypdfium, or model execution was
run in this slice.

## Behavior

- `LayoutAnnotator` preserves supplied `image_bbox` only when it has positive
  width and height.
- `LayoutAnnotator` rejects normalized layout bbox rows when an explicit
  `image_bbox` is present but unusable, while preserving absolute positive-area
  fallback rows.
- `LayoutOrderer` applies the same boundary for supplied order metadata and
  ordering rows, preventing normalized order rows from reordering selected
  pdftext blocks without a usable image extent.
- WordPress supplied-document conversion keeps the selected text paragraphs,
  skips the invalid normalized title/order rows, and keeps raw payload strings
  out of serialized review metadata.

## Evidence

Baseline before adding the cases:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 467 assertions, 0 failures`.

Red after adding the zero-area `image_bbox` cases before source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 481 assertions, 2 failures`. The direct order case
trusted a normalized row even though the supplied `image_bbox` had zero width;
the converter case expected the same fail-closed boundary for layout/order
review metadata.

Green after source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 498 assertions, 0 failures`.

Adjacent shared-helper check:

```bash
php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
```

Result: `3 test files, 871 assertions, 0 failures`.

Syntax and hygiene:

```bash
php -l lanes/markerpdf/src/LayoutOrderer.php
php -l lanes/markerpdf/src/LayoutAnnotator.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-image-bbox-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: all PHP files report no syntax errors; `lane-status json ok`;
`git diff --check -- lanes/markerpdf` produced no output.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-image-bbox-currentbase.php
```

Result: emits two Gutenberg paragraphs in selected order and reports
`layout_image_bbox_dropped=true`, `order_image_bbox_dropped=true`,
`normalized_layout_row_skipped=true`, `normalized_order_row_skipped=true`,
`first_before_second=true`, `invalid_title_not_promoted=true`,
`cover_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP
pdftext-dictionary, supplied-layout, supplied-order, artifact-selection, and
WordPress supplied-document conversion components under the no-GPU markerPDF
scope. Mapped upstream denominator is unchanged; focused PHP behavior coverage
increases by two cases and one WordPress smoke.

## Non-overlap

This slice does not repeat accepted row-level page-marker filtering, typed
payload wrappers, page-range/page-num/page-idx alignment, array/polygon bbox
normalization, JSON-decoded artifact normalization, duplicate artifact
rejection, non-finite bbox/marker rejection, zero-overlap grouping, or inline
image decode boundaries. It specifically covers normalized layout/order rows
paired with an explicitly supplied zero-area `image_bbox`.
