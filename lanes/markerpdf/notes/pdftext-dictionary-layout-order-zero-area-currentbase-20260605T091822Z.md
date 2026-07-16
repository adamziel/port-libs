# markerPDF pdftext dictionary layout/order zero-area geometry boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T091822Z`

Accepted base: `fc832a46164b6beed08847bc9302047fb42572bd`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so selected pdftext pages are built before layout/order handoff.
- `marker/layout/order.py::sort_blocks_in_reading_order()` applies supplied ordering geometry by overlap. Native no-GPU supplied order rows therefore need usable positive geometry before changing selected-page reading order.

## Implemented Behavior

- `LayoutOrderer` now drops supplied order boxes with zero width or zero height.
- `LayoutOrderer::sortBlocksInReadingOrder()` now ignores order rows with zero block overlap instead of assigning every block a zero-intersection position.
- Selected pdftext dictionary pages preserve source order when supplied order geometry is unusable, while valid layout metadata and selected-page artifact matching remain unchanged.
- Added a WordPress smoke for zero-area supplied order geometry.

## Red-First Evidence

After adding the regressions and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
1 test files, 279 assertions, 1 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 776 assertions, 1 failures
```

Both failures showed zero-width/zero-height order boxes reordering the selected pdftext page.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
1 test files, 287 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 779 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php
1 test files, 25 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-zero-area-currentbase.php
```

The example emits `zero_area_order_boxes_excluded=true`, `source_order_preserved=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +12 focused assertions, +1 mapped manifest behavior, and +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, sparse keyed artifact matching, wrapper-list matching, numeric-string geometry normalization, malformed non-numeric order rows, selected-index markers, duplicate keyed reuse prevention, payload marker fallback, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically zero-area and zero-overlap supplied order geometry before selected pdftext dictionary pages are converted to WordPress paragraphs.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and table/equation handoffs.
