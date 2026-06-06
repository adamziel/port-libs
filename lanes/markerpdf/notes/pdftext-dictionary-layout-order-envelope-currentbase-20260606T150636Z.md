# markerPDF pdftext dictionary layout/order envelope boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T150636Z`

Base accepted HEAD: `bc375bdb07bbeeec6db609f2a5c69fe6a4b80ba4`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains pdftext dictionary pages, trims them to the selected page range, then zips supplied layout/order predictions with those selected pages before block annotation and reading-order sorting.
- The native no-GPU PHP boundary owns supplied artifacts that are cached in pdftext-shaped envelopes. A cached `dictionary_output` or `pages` artifact envelope must be unwrapped before page-marker selection; otherwise a stale cover-page envelope can be treated as one positional artifact and block current-page ordering.

## Implementation

- `PdfPageArtifactSelector::normalizeSuppliedArtifacts()` now unwraps non-payload `pages` and `dictionary_output` envelopes before selected-page matching.
- Direct artifacts that already contain payload keys such as `image`, `image_bbox`, `bboxes`, `layout_result`, `order_result`, `page_data`, or `page_result` are left intact.
- The selected artifact is still sanitized by `LayoutOrderer`/`LayoutAnnotator`, so envelope and row `raw_payload` text remains excluded from WordPress metadata and visible paragraphs.

## Evidence

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 501 assertions / 1 failure
FAIL unwraps dictionary-output artifact envelopes before selected pdftext layout order assignment
Expected current selected-page order, actual stale source order.
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 513 assertions / 0 failures
```

Adjacent dictionary/order family:

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 3 test files / 807 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-envelope-currentbase.php
```

The smoke emits `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `selected_page_ordered_by_unwrapped_dictionary_output=true`, `cover_excluded=true`, `appendix_excluded=true`, `envelope_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted pdftext dictionary core normalization, direct page envelopes, named `dictionary_output` page envelopes for text extraction, selected page/order marker aliases, trusted metadata fallback, typed `layout_result`/`order_result` wrappers, `page_data`/`page_result` wrappers, duplicate artifact rejection, non-finite geometry rejection, row-level stale page-marker filtering, or normalized image-bbox rejection. The bounded behavior is only cached layout/order artifact lists wrapped inside non-payload `dictionary_output` or `pages` envelopes before selected-page matching.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary adapter, supplied artifact selector, layout/order sanitizer, WordPress supplied-document converter, and existing no-GPU smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside this markerPDF slice.
