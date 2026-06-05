# markerPDF pdftext dictionary layout/order array markers

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T061331Z`

Accepted base: `f35a619c7f21a255877365c107bd8809c41d57e8`

## Source truth

- Upstream `sddai/markerPDF` pinned source maps pdftext dictionary output through selected page ranges before conversion: `marker/pdf/extract_text.py::get_text_blocks()` calls `dictionary_output(..., page_range=...)`.
- Upstream `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` before model layout/order handoff, then layout/order predictions are zipped with the selected page list in `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()`.
- This no-GPU PHP port cannot run Surya/Texify/OCR models in the current lane, so the faithful behavior is native selected-page alignment for supplied layout/order artifacts and fail-closed handling when adapter page identity is ambiguous.

## Implementation

- `PdfPageArtifactSelector` now treats list-valued page marker fields as marker data. Singleton list markers such as `page => [741]` normalize to the integer page identity; multi-value lists such as `page => [720, 721]` remain explicit marker data and block positional fallback.
- `LayoutAnnotator` and `LayoutOrderer` now preserve list-wrapped page markers only when the list resolves to exactly one integer. Conflicting lists return `null` at annotation/order application time.
- Added extractor and converter tests covering singleton list markers, selected-page slicing, ambiguous marker rejection, and WordPress-visible supplied-boundary ordering.
- Added `wordpress-pdftext-dictionary-layout-order-array-marker-currentbase.php` smoke for the WordPress import path.

## Red-first evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 931 assertions, 3 failures
```

Observed failures:

- Singleton list marker layout/order artifacts stayed source ordered instead of aligning to the selected pdftext page.
- Ambiguous multi-value marker artifacts were positionally assigned to the selected page.
- Supplied converter kept both layout artifacts instead of trimming to the selected singleton marker.

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php

php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php

php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
No syntax errors detected in lanes/markerpdf/tests/SuppliedDocumentConverterTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-array-marker-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-array-marker-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 951 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
4 test files, 1014 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-array-marker-currentbase.php
```

The example emits `singleton_array_marker_normalized=true`, `ambiguous_array_not_positionally_assigned=true`, `first_before_second=true`, `ambiguous_source_order_preserved=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This patch does not repeat accepted pdftext dictionary layout/order coverage for supplied range trimming, sparse keyed matching, selected index identity, keyed collision handling, partial-keyed artifacts, wrapped-keyed artifacts, payload markers, string markers, decimal markers, conflicting identity, or payload-boundary sanitation. It is bounded to list-valued page marker normalization and ambiguous list-marker fail-closed behavior.

## Dependency closure

No new support component is needed. The patch reuses existing native PHP supplied-boundary selection, layout annotation, order assignment, and WordPress conversion smoke infrastructure. It does not execute Python, GPU/model OCR, Surya/Texify/Torch, external PDF tools, or online services.

## Next task

Continue native markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
