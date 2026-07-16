# markerpdf pdftext dictionary layout-order payload boundary current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T031950Z`

Accepted base: `ade3bedea1d5f41d2a42f4498c3f970f11a0b9a1`

## Source truth

- Upstream `sddai/markerPDF` pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF pages through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` over the selected `page_range` and enumerates only those selected dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/convert.py::convert_single_pdf()` trims pages before `start_page` before rendering layout/order images, then supplies selected pages to detection/layout/OCR/order stages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- Upstream `marker/layout/order.py::surya_order()` zips Surya ordering results to selected Marker pages, and `sort_blocks_in_reading_order()` uses the order result `image_bbox` and `bboxes` geometry for overlap sorting: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
- Locked `pdftext==0.3.18` `dictionary_output()` strips block/line/span payloads down to dictionary page text geometry before markerPDF conversion; it is not part of Surya order metadata: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Implementation

- `LayoutOrderer::runWithSuppliedOrder()` now assigns a sanitized order result to each selected page.
- The sanitizer preserves `image_bbox`, `bboxes`, and scalar page identity markers (`page`, `pnum`, source/selected indexes, and page-number variants), including markers found in shallow/nested adapter wrappers.
- The sanitizer drops nested `pdftext` dictionary copies, raw adapter/private payloads, raw order-result `blocks`, and other non-order payloads before page metadata reaches WordPress import/review.

## Red-first evidence

Before the implementation, the new focused regression failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL keeps matched layout order artifacts from leaking nested pdftext dictionary payloads
Values are not identical
Expected: 401
Actual: NULL
1 test files, 168 assertions, 1 failures
```

The failure showed that a matched order artifact selected through nested metadata was copied without a normalized top-level page marker and still carried nested adapter payloads.

## Verification

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
2 test files, 205 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 665 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php
exit 0; reports order_page_marker_preserved=true, order_geometry_preserved=true, visible_columns_in_reading_order=true, order_payload_excluded=true

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native `pdf-text-dictionary-core`, `PdfPageArtifactSelector`, and supplied layout-order boundary components. No Python, OCR/model, Surya/Torch, pypdfium, JavaScript, browser, or external PDF tooling was executed.

## Non-overlap

This does not repeat full-document artifact trimming, sparse keyed matching, selected-index matching, page/source collision rejection, conflicting identity rejection, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically sanitizing matched layout-order artifacts so adapter-carried pdftext dictionaries and raw payloads cannot leak beyond the order-assignment boundary.
