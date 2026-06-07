# markerpdf pdftext dictionary layout-order plural marker current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260607T002750Z`

Accepted base: `3a8058f7395669b2624b4a95a60e0fcfd8045b07`

## Source truth

The bounded upstream contract for this no-GPU markerPDF slice is the existing lane mapping for searchable pdftext dictionary conversion: `dictionary_output(... page_range ...)` emits page dictionaries, `convert.py` trims selected pages before supplied layout/order handoff, and `marker/layout/order.py` zips order predictions with the selected pages. This patch keeps that selected-page zip contract in native PHP and only widens accepted page-identity marker aliases at the supplied artifact boundary.

The upstream markerPDF checkout is not hydrated in this isolated worktree, so no upstream Python, PDFium, Surya, Texify, Torch, OCR, or model runner was executed.

## Implemented behavior

`PdfPageArtifactSelector`, `LayoutAnnotator`, and `LayoutOrderer` now normalize plural pdftext page marker aliases before matching supplied layout/order artifacts:

- exact page aliases: `pnums`, `pdftext_pages`, `source_pages`, and `document_pages`
- one-based page aliases: `page_numbers` and `page_nums`
- selected page aliases: `selected_page_numbers`, `trimmed_page_numbers`, `relative_page_numbers`, `selected_page_nums`, `trimmed_page_nums`, and `relative_page_nums`

Sparse artifacts with `selected_page_numbers: [2]` now attach to the second selected WordPress page after `start_page`/`max_pages` trimming. Ambiguous multi-value plural markers such as `page_numbers: [5401, 5402]` still fail closed through the existing scalar marker guard, and raw layout/order payloads remain excluded from visible output.

## Evidence

Focused baseline before this patch:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
# 1 test files, 678 assertions, 0 failures
```

Focused after patch:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
# 1 test files, 715 assertions, 0 failures
```

Syntax checks:

```sh
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
php -l lanes/markerpdf/src/LayoutAnnotator.php
php -l lanes/markerpdf/src/LayoutOrderer.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-plural-marker-currentbase.php
# No syntax errors detected
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-plural-marker-currentbase.php
# assigned_only_second_selected_page=true
# first_selected_page_source_order_preserved=true
# second_selected_page_reordered=true
# cover_excluded=true
# appendix_excluded=true
# payloads_excluded=true
# executes_python_or_models=false
# executes_external_pdf_tools=false
```

Focused delta: +2 PASS cases and +37 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`; +1 WordPress smoke/example; +1 mapped manifest behavior row.

## Dependency closure

No new support component is needed. The slice reuses native PHP `PdfPageArtifactSelector`, `LayoutAnnotator`, `LayoutOrderer`, `PdfTextDocumentExtractor`, and `SuppliedDocumentConverter` behavior. No Python, PDFium/pypdfium, Surya, Texify, Torch, CUDA, OCR/model execution, browser, or external PDF tools were run or added.

## Non-overlap

This does not repeat accepted `page_range` trimming, singular `page_num`/`page_number` markers, selected-index matching, list-valued singular marker handling, string/decimal/signed markers, marker precedence, page-index collision handling, wrapper/envelope unwrapping, duplicate-artifact ambiguity, zero-overlap ordering, nonfinite/zero-area geometry, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The behavior is limited to plural pdftext page marker alias normalization before selected-page layout/order assignment.

Root harness: not run - isolated micro-slice.
