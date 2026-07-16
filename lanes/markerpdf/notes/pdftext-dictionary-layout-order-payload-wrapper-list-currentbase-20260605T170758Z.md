# markerPDF pdftext dictionary layout/order payload wrapper-list boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T170758Z`

Accepted base: `4cc4c34e199d77834513eab45aee0fc3c1d75619`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so selected pdftext dictionary pages are already trimmed before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order handoff.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip one prediction object per selected Marker page. Native supplied-boundary adapters may wrap that object, but a typed wrapper containing multiple dictionaries is ambiguous and must not be treated as one trusted model payload.

## Implemented Behavior

- `LayoutAnnotator` now skips assignment for layout artifacts whose typed payload wrappers (`layout`, `layout_result`, `prediction`, `result`, `model_output`, or `output`) contain more than one dictionary.
- `LayoutOrderer` applies the same fail-closed boundary for typed order payload wrappers (`order`, `order_result`, `prediction`, `result`, `model_output`, or `output`).
- Trusted outer page metadata can still select the candidate for review, but ambiguous model geometry is not assigned to the page. Source pdftext block order is preserved and raw payload dictionaries stay out of result metadata and WordPress text.
- Added a focused extractor regression and a WordPress supplied-document smoke for the typed payload wrapper-list boundary.

## Red-First Evidence

Before the implementation change, the focused probe showed `order_result` with two dictionaries was unwrapped and applied:

```text
"texts": [
    "First payload-list column has no trusted payload",
    "Second payload-list column remains source ordered"
],
"assigned": 1
```

The expected behavior is source-order preservation with `assigned_pages=0`.

## Verification

```text
php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 182 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1323 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase.php

git diff --check -- lanes/markerpdf
passed
```

The example emits `layout_payload_wrapper_list_rejected=true`, `order_payload_wrapper_list_rejected=true`, `layout_artifact_review_count=1`, `order_artifact_review_count=1`, `source_order_preserved=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case and +9 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, plus +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, sparse keyed matching, selected-index matching, page-range markers, singleton or metadata wrapper-list matching, mixed metadata wrapper-list payload rejection, source-page payload fallback, stale typed payload marker fallback, normalized bbox handling, zero-area order geometry rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically multi-dictionary typed layout/order payload wrappers after page selection.

## Next Task

Continue native markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
