# markerPDF pdftext dictionary layout/order page_num boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T190204Z`

Accepted base: `6eabc470c32c0f122118ac788fbbcb8021d0420e`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker_app.py::get_page_image()` accepts a one-based `page_num` and hands PDFium `page_indices=[page_num - 1]`, so native adapters must treat `page_num` as one-based page identity rather than zero-based source index.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, preserving selected-page range semantics before downstream layout/order handoff.
- `marker/convert.py::convert_single_pdf()` calls `get_text_blocks(..., max_pages=..., start_page=...)`, trims the PDFium document for `start_page`, then runs `surya_layout()` and `surya_order()` on the selected page images and pages.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip prediction results with selected Marker pages. Native supplied-boundary adapters must therefore align sparse artifacts by trusted page identity before zip-style assignment.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats `page_num` as a one-based alias for `page_number`.
- The selected-page aliases `selected_page_num`, `trimmed_page_num`, and `relative_page_num` now match the existing selected/trimmed/relative one-based page-number markers.
- `LayoutAnnotator` and `LayoutOrderer` preserve the same trusted `page_num` markers on assigned layout/order results and continue to exclude stale typed payload page fields from nested `layout_result` / `order_result` dictionaries.
- Added a focused extractor/converter regression and a WordPress smoke proving cover and appendix artifacts keyed by `page_num` are trimmed out, the selected-page order result is applied, and raw layout/order payload strings remain hidden.

## Red-First Evidence

After adding the focused regression and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Expected: array (0 => 'layout', 1 => 'order')
Actual: array ()
1 test files, 203 assertions, 1 failures
```

The failure showed that sparse supplied layout/order artifacts with trusted `metadata.page_num` did not match the selected pdftext page, so no supplied boundaries were assigned.

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php && php -l lanes/markerpdf/src/LayoutAnnotator.php && php -l lanes/markerpdf/src/LayoutOrderer.php && php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-num-currentbase.php
No syntax errors detected in changed PHP files

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 223 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
5 test files, 1364 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-num-currentbase.php
```

The example emits `page_num_layout_artifacts_trimmed=true`, `page_num_order_artifacts_trimmed=true`, `page_num_metadata_matched=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `stale_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +22 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat previous sparse `page_number` matching, selected-index matching, page-range markers, exact page markers, nested adapter wrapper matching, wrapper-list matching, mixed wrapper-list payload rejection, source-page payload fallback, typed wrapper payload unwrapping, stale payload marker sanitation, normalized bbox handling, zero-area order geometry rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically one-based `page_num` metadata and selected/trimmed/relative `*_page_num` aliases before selected-page layout/order assignment.

## Next Task

Continue native markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
