# markerPDF pdftext dictionary layout/order explicit pdftext envelope boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T202235Z`

Session: `port-dev-markerpdf-pdf-dictionary-layout-20260606T202235Z`

Base accepted HEAD: `7ae9bd829c4ac182c3749c9a2dca4c1799cec369`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, which consumes selected `pdftext.extraction.dictionary_output(...)` page dictionaries before layout/order handoff.
- Upstream `marker/convert.py::convert_single_pdf()` trims pages before layout/order image and model-result assignment, then `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip predictions with the selected Marker pages.
- Under the current no-GPU markerPDF directive, live Surya/PDFium/pdftext/model execution is out of scope. The native PHP boundary accepts supplied layout/order artifacts, so cache envelopes named `pdftext` must follow the same selected-page artifact-list behavior as accepted `pages` and `dictionary_output` envelopes.

## Implemented Behavior

- `PdfPageArtifactSelector` now unwraps non-payload `pdftext` page-list envelopes before selected-page artifact matching.
- Cached `pdftext.pages` layout images, layout results, order images, and order results now trim to the selected pdftext dictionary page instead of remaining as one positional wrapper.
- Direct payload guards remain in place: nested pdftext page dictionaries with `blocks`/`bboxes` stay payload/fallback data rather than trusted artifact-list envelopes.
- Added focused extractor and supplied-document regressions proving cover/appendix artifacts are excluded, selected layout/order geometry applies, private payload strings do not leak, and WordPress heading/body order follows the selected artifact payload.
- Added `wordpress-pdftext-dictionary-layout-order-pdftext-envelope-currentbase.php` smoke.

## Red-First Evidence

After adding the focused regressions and before the selector change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 619 assertions, 2 failures
```

The failures showed the explicit `pdftext.pages` artifact envelope stayed as a single selected artifact. Order payloads were empty, blocks remained source ordered, and the WordPress converter did not promote the selected Title layout row.

## Verification

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-pdftext-envelope-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-pdftext-envelope-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 646 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
5 test files, 1817 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-pdftext-envelope-currentbase.php
```

The WordPress smoke emits `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `selected_page_ordered_by_unwrapped_pdftext=true`, `cover_excluded=true`, `appendix_excluded=true`, `envelope_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2673 -> 2675`
- `wordpressScenarios`: `2253 -> 2254`
- Focused test coverage: +2 PASS cases in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`; focused file after fix is `646` assertions.
- Mapped manifest denominator: unchanged.

## Non-Overlap

This does not repeat accepted core pdftext dictionary page-envelope unwrapping, named `dictionary_output` artifact envelopes, direct `pages`/`dictionary_output` payload envelopes, typed direct payload wrappers, JSON-decoded artifact normalization, `pdftext_source` page metadata, source-payload fallback, wrapper-list ambiguity rejection, normalized/named/polygon bbox handling, non-finite/zero-area geometry guards, zero-overlap grouping, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary work. The bounded behavior is only non-payload supplied layout/order artifact lists wrapped under explicit `pdftext.pages` cache envelopes.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, the supplied artifact selector, layout annotation, layout ordering, the supplied-document converter, Markdown finalization, and the WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark/model downloads, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
