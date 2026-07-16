# markerPDF pdftext dictionary layout sanitized-payload current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T050049Z`

Accepted base: `11a7b6924e8b549c836158a54da8e2a995e7ea6f`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF pages to `pdftext.extraction.dictionary_output(...)` over the selected page range before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before layout/order rendering, so supplied native artifacts must be selected-page aligned before annotation/order assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/layout.py::surya_layout()` attaches layout model predictions to pages for annotation. Native supplied predictions may carry adapter page identity, but nested pdftext page copies and raw/debug payloads are not layout metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/layout.py

## Implemented behavior

- `LayoutAnnotator::runWithSuppliedLayouts()` now assigns sanitized layout results to selected pages.
- The sanitizer preserves `image_bbox`, `bboxes`, and scalar page markers from top-level or shallow/nested adapter wrappers.
- Nested `pdftext` dictionary copies, raw layout blocks, adapter metadata envelopes, segmentation maps, raw bytes, and rendered-image payloads are excluded before page layout metadata reaches WordPress review/annotation paths.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
FAIL keeps matched layout artifacts from leaking nested pdftext dictionary payloads
Values are not identical
Expected: 711
Actual: NULL
1 test files, 27 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/tests/LayoutAnnotatorTest.php
No syntax errors detected in lanes/markerpdf/tests/LayoutAnnotatorTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-sanitized-payload-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-sanitized-payload-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
1 test files, 38 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
4 test files, 957 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-sanitized-payload-currentbase.php
exit 0; emits layout_page_marker_preserved=true, layout_geometry_preserved=true, layout_payload_excluded=true, visible_wordpress_text="Selected layout payload boundary paragraph.", executes_python_or_models=false, and executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range artifact alignment, supplied layout annotation, Markdown post-processing, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout model execution, Texify, Torch/model downloads, Streamlit/FastAPI workers, and external PDF tools remain intentionally outside the no-GPU markerPDF scope.

## Non-overlap

This does not repeat full-document artifact trimming, sparse keyed matching, wrapper marker discovery, selected-index matching, duplicate artifact replay prevention, stale nested pdftext marker fallback, order-result payload sanitation, selected-count mismatch exclusion, page/source collision rejection, conflicting identity rejection, pdftext dictionary sorting, keep-chars sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically sanitizing matched layout prediction payloads before selected-page annotation.
