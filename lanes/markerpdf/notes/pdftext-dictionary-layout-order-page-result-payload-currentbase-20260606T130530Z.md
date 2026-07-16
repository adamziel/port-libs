# markerPDF pdftext dictionary layout/order page-result payload boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T130530Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)`, then Marker consumes only the selected page dictionaries.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order rendering.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip one model result object per selected Marker page. Native supplied-boundary adapters may store that result object under page-style envelopes such as `page_data` or `page_result`; the envelope is not WordPress-visible payload.

## Implemented Behavior

- `LayoutAnnotator` now treats `page_data`, `page_result`, `result_metadata`, and `artifact_metadata` as bounded single-payload envelopes when they carry layout `image_bbox` and `bboxes`.
- `LayoutOrderer` applies the same payload-envelope unwrapping for supplied order `image_bbox` and `bboxes`.
- Existing marker precedence, malformed-marker rejection, multi-dictionary ambiguity checks, row-level page-marker filtering, and payload sanitization remain unchanged.
- Added focused extractor coverage for a selected `page_result` order payload envelope.
- Added WordPress supplied-document coverage and a smoke for `page_data` layout payloads plus `page_result` order payloads.

## Red-First Evidence

Before the source edit, the new focused cases failed because the selected payload envelope was used for page identity but not for model geometry:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 448 assertions, 2 failures

FAIL unwraps adapter page-result order payload envelopes before selected pdftext layout assignment
Expected [First page-result payload column, Second page-result payload column]
Actual   [Second page-result payload column, First page-result payload column]

FAIL unwraps adapter page-result layout and order payload envelopes for WordPress imports
String does not contain '# First Converter Page-Result Heading.'
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 467 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-result-payload-currentbase.php
```

The example emits `layout_payload_envelope_unwrapped=true`, `order_payload_envelope_unwrapped=true`, `heading_promoted=true`, `body_preserved=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +19 focused assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, plus +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, cached pdftext dictionary envelope source-page counting, JSON-decoded artifact normalization, top-level keyed matching, shallow/nested metadata wrapper matching, typed `layout_result`/`order_result` payload unwrapping, stale typed payload marker precedence, mixed wrapper-list rejection, multi-dictionary typed payload rejection, `pdftext_source`, page index/page number aliases, row-level page-marker filtering, duplicate artifact rejection, normalized/named/polygon/non-finite/zero-area bbox handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically single page-result/page-data adapter envelopes that carry the actual supplied layout/order model payload for a selected pdftext dictionary page.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser/converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
