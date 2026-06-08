# markerPDF pdftext dictionary layout/order direct option envelope current base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T190454Z`
Base: `3b6348f2093c6ce73bfa5234c770456166128de9`
Lane: `markerpdf`

## Source truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)` and then enumerates the selected page dictionaries into Marker pages.
- Upstream layout/order assignment zips supplied page images and predictions with the selected page list. Native no-GPU PHP adapters may serialize supplied `lowres_images`, `layout_results`, `order_images`, and `order_results` as explicit `pages`, `dictionary_output`, `pdftext`, or `page_map` envelopes; those should reach the existing selected-page artifact selector without requiring an extra list wrapper.

## Implementation

- `SuppliedDocumentConverter::pageArtifactOption()` now accepts a top-level artifact page-list envelope when `PdfPageArtifactSelector::normalizeSuppliedArtifacts()` unwraps it to at least one selectable image/layout/order payload.
- Metadata-only envelopes remain rejected, and raw pdftext page-copy envelopes are not treated as selectable model/image artifacts.
- Added focused coverage proving direct option envelopes unwrap before WordPress layout/order import while stale cover payloads and internal selector keys stay out of visible text and metadata.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectOptionEnvelopeCurrentBaseTest.php
FAIL accepts direct artifact option envelopes before selected WordPress layout order import
markerPDF supplied document option lowres_images must be a list or source-page keyed map.
PASS rejects metadata-only direct artifact option envelopes before supplied import
1 test files, 1 assertions, 1 failures
```

## Focused verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectOptionEnvelopeCurrentBaseTest.php
1 test files, 24 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-direct-option-envelope-currentbase.php
```

The smoke emits `layout_direct_option_envelope_unwrapped=true`, `order_direct_option_envelope_unwrapped=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, supplied page-artifact selector, layout annotator, layout orderer, supplied document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF lane rule.

## Non-overlap

This does not repeat accepted supplied-range slicing, source-keyed map selection, page-map envelopes inside a list wrapper, typed JSON payload envelopes, direct payload envelopes inside a selected artifact row, page marker aliases, row-level marker filtering, duplicate-key rejection, ambiguous wrapper rejection, geometry/bbox/polygon sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table/equation supplied-boundary handoffs, OCR, or model parity. The bounded behavior is specifically top-level supplied artifact option envelopes that were already supported by the selector but rejected by converter option validation.

## Next task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser/converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
