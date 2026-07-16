# markerPDF pdftext dictionary layout/order wrapper-list boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T064752Z`

Accepted base: `513c457363a14f83e08080a9ac834402b5c747ec`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so selected pdftext pages are already trimmed before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order model handoff.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip predictions with the selected Marker pages. Native supplied adapters that serialize page metadata as wrapper-list dictionaries must therefore be aligned before zip-style assignment, and ambiguous wrapper lists must fail closed.

## Implemented Behavior

- `PdfPageArtifactSelector` now descends into dictionary entries inside wrapper-list values such as `metadata => [['page' => 841]]` and `page_metadata => [['page' => 841]]`.
- Singleton wrapper-list dictionaries match the selected pdftext page before layout/order assignment.
- Multi-entry wrapper-list dictionaries remain marker-bearing and reject positional fallback, preventing a cover-page artifact from reordering the selected page.
- `LayoutAnnotator` and `LayoutOrderer` use the same wrapper-list marker traversal when preserving safe scalar page metadata on assigned layout/order rows.
- Added extractor and converter regressions plus a WordPress smoke for singleton wrapper-list matching and ambiguous wrapper-list rejection.

## Red-First Evidence

After adding the focused regressions and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 962 assertions, 4 failures
```

Failures showed singleton wrapper-list metadata was ignored, causing source-ordered selected text or extra artifacts, and ambiguous multi-entry wrapper lists fell back to positional layout/order assignment.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 988 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
4 test files, 1051 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-wrapper-list-currentbase.php
```

The example emits `wrapper_list_layout_artifacts_trimmed=true`, `wrapper_list_order_artifacts_trimmed=true`, `singleton_wrapper_list_matched=true`, `ambiguous_wrapper_list_rejected=true`, `first_before_second=true`, `ambiguous_source_order_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +4 focused PASS cases and +26 assertions over the red-first focused extractor/converter run after adding the regressions.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, top-level keyed artifact matching, shallow dictionary-wrapper matching, nested adapter wrapper matching, selected-index matching, page-index collision protection, conflicting identity rejection, partial sparse-keyed alignment, duplicate-keyed reuse prevention, payload-marker fallback, payload sanitation, string/decimal/list-valued marker normalization, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically dictionary entries inside supplied layout/order page-marker wrapper lists.

## Next Task

Continue native markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
