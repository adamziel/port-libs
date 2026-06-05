# markerPDF pdftext dictionary layout/order non-finite marker boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T182543Z`

Base accepted HEAD: `d18d8b7d427ab62c97ee01acf72ba9cfa535c34b`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so selected pdftext dictionary pages are already trimmed before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before layout/order handoff.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip predictions with the selected Marker pages. Native supplied-boundary adapters therefore must reject malformed page identity instead of casting it into a selected page.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats malformed page-marker fields as marker-bearing but unmatchable, so `INF`, `-INF`, `NAN`, empty lists, and mixed invalid page-marker lists cannot fall back to positional assignment.
- `LayoutAnnotator` and `LayoutOrderer` use the same finite integer marker boundary for direct supplied layout/order calls, while preserving accepted behavior for valid integers, integer-valued floats, signed/whitespace integer strings, and valid list markers.
- Non-finite page markers no longer cast to `0`, so first-page pdftext dictionary imports cannot accidentally trust malformed layout/order artifacts.
- Added a focused extractor regression and a WordPress supplied-document smoke proving malformed layout/order markers are rejected and payload text stays out of output.

## Red-First Evidence

After adding the focused regression and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
PHP Warning: The float INF is not representable as an int, cast occurred in lanes/markerpdf/src/PdfPageArtifactSelector.php
PHP Warning: The float INF is not representable as an int, cast occurred in lanes/markerpdf/src/LayoutOrderer.php
FAIL rejects non-finite supplied page markers before first-page pdftext layout order assignment
Expected [Second nonfinite marker column remains source ordered, First nonfinite marker column has no trusted order]
Actual   [First nonfinite marker column has no trusted order, Second nonfinite marker column remains source ordered]
1 test files, 195 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php && php -l lanes/markerpdf/src/LayoutAnnotator.php && php -l lanes/markerpdf/src/LayoutOrderer.php && php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nonfinite-marker-currentbase.php
No syntax errors detected in changed PHP files

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 201 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
3 test files, 276 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
5 test files, 1342 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nonfinite-marker-currentbase.php
```

The WordPress smoke emits `malformed_layout_marker_rejected=true`, `malformed_order_marker_rejected=true`, `nonfinite_marker_not_cast_to_zero=true`, `source_order_preserved=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +9 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat pdftext core finite-number validation, valid string/decimal/signed/list page marker normalization, ambiguous array or wrapper-list rejection, mixed wrapper-list payload rejection, trusted metadata precedence, typed result wrapper unwrapping, page-range metadata matching, sparse keyed artifact matching, source-page payload fallback, normalized bbox handling, zero-area order geometry rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically non-finite supplied layout/order page identity before first-page pdftext dictionary assignment.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
