# markerPDF pdftext dictionary layout/order signed-marker boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T084000Z`

Accepted base: `ef4cf6dacf5f14d3905927d3fba9b6ca3557990c`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` over the selected page range before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before layout/order images are rendered.
- PDF numeric operands may be signed. Native supplied adapters can therefore serialize page identity as `+951` or `+951.0`; these markers must align sparse layout/order artifacts before upstream-style zip assignment.

## Implemented Behavior

- `PdfPageArtifactSelector`, `LayoutAnnotator`, and `LayoutOrderer` now accept optional `+` signs in numeric string page markers.
- Sparse layout/order artifacts keyed as `+950` and `+951.0` are matched to the selected pdftext page before assignment, so cover artifacts cannot reorder the selected page.
- Sanitized layout/order metadata still stores integer page markers and geometry only; Python, pdftext, pypdfium/PDFium, Surya/Torch, OCR, and external PDF tools are not executed.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
FAIL normalizes signed numeric page markers before selected pdftext layout order assignment
Expected: [First signed-marker selected column, Second signed-marker selected column]
Actual: [Second signed-marker selected column, First signed-marker selected column]
1 test files, 271 assertions, 1 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
FAIL normalizes signed numeric page markers before supplied layout and order alignment
Expected: 1
Actual: 2
1 test files, 756 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
1 test files, 276 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 766 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
2 test files, 63 assertions, 0 failures
```

The WordPress smoke `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-signed-marker-currentbase.php` emits `signed_marker_page_preserved=true`, `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1632 -> 1634`
- `wordpressScenarios`: `1506 -> 1507`
- Focused assertions: `PdfTextDocumentExtractorTest.php` `268 -> 276`; `SuppliedDocumentConverterTest.php` `753 -> 766`

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range artifact alignment, supplied layout annotation, supplied ordering, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally outside the no-GPU markerPDF scope.

## Non-Overlap

This does not repeat full-document artifact trimming, sparse direct page-key matching, whitespace/decimal string markers, array markers, wrapper-list markers, nested adapter wrappers, selected-index matching, source-page aliases, duplicate keyed replay prevention, stale nested pdftext marker precedence, numeric-string order bboxes, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically optional plus signs in numeric page marker strings before selected pdftext layout/order alignment.
