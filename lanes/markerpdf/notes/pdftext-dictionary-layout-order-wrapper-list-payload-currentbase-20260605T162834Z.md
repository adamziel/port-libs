# markerPDF pdftext dictionary layout/order mixed wrapper-list payload boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T162834Z`

Accepted base: `c0e71447bb6ce34af94a2d4d96a552f5aa1446a1`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so selected pdftext dictionary pages are already trimmed before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order handoff.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip predictions with the selected Marker pages. Native supplied-boundary adapters must therefore align artifacts by trusted page identity before zip-style assignment.
- A previous accepted slice mapped singleton wrapper-list metadata such as `metadata => [['page' => 841]]` as trusted. This slice tightens the boundary for mixed wrapper lists such as `metadata => [['page' => 2401], ['raw_payload' => ...]]`, where the page dictionary sits beside a separate payload dictionary and the list is ambiguous.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats multi-dictionary page-marker wrapper lists as marker-bearing but unmatchable unless a normal outer marker is also present.
- `LayoutAnnotator` and `LayoutOrderer` use the same marker-source boundary, so sanitization does not fall through to stale payload wrapper page keys after ambiguous metadata.
- The selector no longer descends into mixed list-valued metadata wrappers to extract one page marker while ignoring adjacent payload dictionaries.
- Added a focused extractor regression and a WordPress supplied-document smoke proving the selected page remains source ordered, supplied layout/order boundaries are not assigned, and payload text stays out of output.

## Red-First Evidence

After adding the focused regression and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Expected [Second mixed wrapper-list column remains source ordered, First mixed wrapper-list column has no trusted order]
Actual   [First mixed wrapper-list column has no trusted order, Second mixed wrapper-list column remains source ordered]
1 test files, 167 assertions, 1 failures
```

The failure showed the selector extracted `page => 2401` from one dictionary in a mixed wrapper list and incorrectly applied supplied ordering to the selected page.

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php && php -l lanes/markerpdf/src/LayoutAnnotator.php && php -l lanes/markerpdf/src/LayoutOrderer.php && php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-wrapper-list-payload-currentbase.php
No syntax errors detected in changed PHP files

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 173 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
5 test files, 1314 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-wrapper-list-payload-currentbase.php
```

The example emits `mixed_wrapper_list_rejected=true`, `layout_artifacts_rejected=true`, `order_artifacts_rejected=true`, `source_order_preserved=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +9 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat singleton wrapper-list matching, ambiguous multi-page wrapper-list rejection, top-level keyed artifact matching, shallow dictionary-wrapper matching, nested adapter wrapper matching, selected-index matching, page-range markers, source-page payload fallback, typed wrapper payload unwrapping, stale payload marker sanitation, normalized bbox handling, zero-area order geometry rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically mixed multi-dictionary supplied-boundary metadata wrappers that contain one page-marker dictionary beside payload dictionaries.

## Next Task

Continue native markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
