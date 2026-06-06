# markerpdf pdftext dictionary layout/order row-page boundary current base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T090614Z`  
Accepted base: `7e1dda7eed0332ada40f56b35726ed2f1251ad54`

## Source truth

Upstream markerPDF gets pdftext `dictionary_output` pages for the selected page
range, renders/trims the same selected PDFium pages for layout/order models,
then zips model results with the selected page list before annotation and
reading-order sorting. The native no-GPU supplied-boundary path should therefore
treat row-level page markers on cached adapter bbox rows as trust boundaries:
rows that explicitly identify another source/document page must not annotate or
reorder the current selected pdftext page.

No live Surya/pdftext/PDFium/model execution was run in this slice.

## Behavior

- `LayoutAnnotator::runWithSuppliedLayouts()` now accepts the selected
  `page_range` context and filters row-level layout bboxes whose page markers
  contradict the selected page.
- `LayoutOrderer::runWithSuppliedOrder()` now accepts the selected `page_range`
  context and filters row-level order bboxes whose page markers contradict the
  selected page.
- Unmarked bbox rows remain accepted, preserving ordinary upstream Surya row
  payloads and existing supplied-artifact behavior.
- Malformed or ambiguous row page markers fail closed.

## Evidence

Red before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 399 assertions, 1 failures`. The new row-page case
failed because a stale layout `Title` row promoted selected text to a heading
and a stale order row reversed the selected columns.

Green after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 405 assertions, 0 failures`.

Additional guard:

```bash
php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
```

Result: `2 test files, 75 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-row-page-currentbase.php
```

Result: emits a Gutenberg paragraph with `First row-marker import column.`
before `Second row-marker import column.` and review flags
`layout_order_assigned=true`, `stale_layout_title_excluded=true`,
`stale_payload_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP
pdftext-dictionary, supplied-layout, supplied-order, and artifact-selection
components under the no-GPU markerPDF scope. Mapped upstream denominator is
unchanged; focused PHP behavior coverage increases by one case and one
WordPress smoke.

## Non-overlap

This slice does not repeat the accepted artifact-level page marker, typed
wrapper, duplicate artifact, normalized bbox, polygon, page range, or
non-finite marker cases. It specifically covers mixed row-level page markers
inside otherwise selected layout/order bbox lists.
