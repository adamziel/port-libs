# markerPDF pdftext dictionary layout/order layout-payload boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T114147Z`

Accepted base: `a20a696ad37cb38330c430dc42489a24868948cb`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates the selected page dictionaries into Marker pages.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before low-resolution layout/order images are generated, so supplied native artifacts must align to selected pages before zip-style assignment.
- `marker/layout/order.py::surya_order()` assigns ordering results by zipping selected pages and model output. The native supplied-layout path mirrors the same selected-page boundary and must treat copied nested pdftext dictionaries as payloads, not stronger adapter identity.

## Implemented Behavior

- `LayoutAnnotator` now treats nested `pdftext` dictionaries as fallback-only page-marker sources, matching the existing `LayoutOrderer` boundary.
- Trusted adapter metadata wrappers such as `metadata => ['document_page' => 771]` are preserved without also copying stale nested `pdftext.page` markers into page layout metadata.
- Payload-only supplied layout artifacts can still fall back to a nested `pdftext.page` marker when no normal adapter metadata carries page identity.
- Added a WordPress smoke showing selected-page layout/order import keeps ordered paragraph text while excluding stale nested pdftext payload text and page markers from layout review metadata.

## Red-First Evidence

Before the implementation change, a direct probe showed:

```text
LayoutAnnotator::runWithSuppliedLayouts(...) preserved document_page=771 and stale page=770 together.
```

That meant a copied nested pdftext payload could leak stale page identity into layout metadata even when trusted adapter metadata already identified the selected document page.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
3 test files, 367 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
5 test files, 1171 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-layout-payload-currentbase.php
```

The example emits `layout_document_page=771`, `stale_layout_pdftext_page_excluded=true`, `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, `stale_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, sparse keyed matching, duplicate keyed reuse prevention, wrapper-list marker extraction, ambiguous array/list rejection, ordering-result payload sanitation, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is layout-side page-marker preservation when trusted adapter metadata and copied nested pdftext payloads disagree.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
