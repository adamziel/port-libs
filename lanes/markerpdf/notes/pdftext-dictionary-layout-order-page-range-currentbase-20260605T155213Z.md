# markerPDF pdftext dictionary layout/order page-range metadata boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T155213Z`

Accepted base: `f071fefb2a76a8e9eb3969229618987f332d5aff`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF pages to `pdftext.extraction.dictionary_output(..., page_range=...)`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before layout/order image rendering.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip layout/order predictions to that selected page list. Native supplied adapters that carry page-range metadata must therefore align by the selected source range before zip-style assignment.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats singleton `page_range`, `source_page_range`, `document_page_range`, and plural page-index metadata fields as source-index markers.
- It also treats singleton `selected_page_range`, `trimmed_page_range`, and `relative_page_range` metadata fields as selected-index markers.
- Ambiguous multi-page range markers remain marker-bearing and fail closed instead of falling back to positional assignment.
- `LayoutAnnotator` and `LayoutOrderer` now use the same range-marker awareness when choosing trusted page-marker sources, so stale nested `layout_result.page` / `order_result.page` payload markers remain fallback-only and are not copied into review metadata beside trusted `metadata.page_range`.
- Added two focused behavior cases and one WordPress smoke.

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php

php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-range-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-range-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 164 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
5 test files, 1305 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-range-currentbase.php
```

The WordPress smoke emits `page_range_metadata_trusted=true`, `page_range_not_copied_to_review_metadata=true`, `stale_typed_payload_excluded=true`, `first_before_second=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +31 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, +1 manifest mapping row, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, page-pixel table recognition, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat full-document supplied range slicing, exact sparse keyed matching, shallow/nested metadata wrapper matching, wrapper-list dictionaries, list-valued `page` markers, string/decimal/signed marker normalization, source/document-page aliases, selected-index matching, page-index collision handling, conflicting source/page identity rejection, duplicate keyed no-replay, typed payload unwrapping, trusted metadata precedence, normalized/zero-area/bbox-list order geometry, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically page-range metadata as supplied layout/order page identity and stale typed payload marker exclusion.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
