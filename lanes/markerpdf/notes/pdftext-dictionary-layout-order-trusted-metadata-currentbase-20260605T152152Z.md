# markerPDF pdftext dictionary layout/order trusted metadata boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T151351Z`

Accepted base: `5b7b0019c317588ee2abf0acee527fa17fea0987`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates the selected pdftext dictionaries into Marker pages.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before rendering layout/order images.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip model results to the selected pages; native supplied adapters may carry trusted page identity outside typed `layout_result` / `order_result` payloads.
- Official raw source checked during this slice:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py`

## Implemented Behavior

- `PdfPageArtifactSelector` now reads page markers in two phases:
  1. root artifact plus trusted metadata wrappers;
  2. typed model payload wrappers and copied source/pdftext payloads only as fallback identity.
- `LayoutAnnotator` and `LayoutOrderer` use the same page-marker precedence while sanitizing page metadata, so stale payload `page` markers are not preserved beside trusted `metadata.document_page`.
- Typed `layout_result` / `order_result` wrappers still provide geometry payloads and still provide page identity when there is no trusted outer metadata.
- Added focused lower-level coverage for `PdfTextDocumentExtractor::getOrderedTextBlocks()`.
- Added WordPress supplied-document coverage and a WordPress smoke for the same trusted-metadata/stale-payload boundary.

## Verification

Baseline before this slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 108 assertions, 0 failures
```

Green after implementation:

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php

php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-trusted-metadata-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-trusted-metadata-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 133 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
5 test files, 1274 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-trusted-metadata-currentbase.php
```

The WordPress smoke emits `trusted_metadata_wins=true`, `stale_typed_payload_markers_fallback_only=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +25 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, top-level keyed matching, shallow/nested metadata wrapper matching, typed result payload unwrapping, copied source/pdftext payload fallback handling, numeric page-marker normalization, normalized order bbox rescaling, zero-area order rejection, bbox-list order inference, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically trusted adapter metadata taking precedence over stale typed layout/order result payload page markers.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
