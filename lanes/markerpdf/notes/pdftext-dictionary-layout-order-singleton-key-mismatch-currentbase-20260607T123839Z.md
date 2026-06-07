# markerPDF pdftext dictionary layout/order singleton-key mismatch boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260607T123839Z`

Session: `port-dev-markerpdf-pdf-dictionary-layout-20260607T123839Z`

Accepted base: `29d163feb6e58f391e305e1c254ebf90840b6728`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and then enumerates the returned selected pages before converting each selected page to Marker `Page` structures: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- Upstream layout/order uses zip-style assignment between selected page images/pages and model results: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/layout.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py`.
- Locked `pdftext` dictionary output keeps page dictionaries ordered after page-range extraction and strips block/line/span payloads to trusted output shape: `https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py`.

## Behavior

- `PdfPageArtifactSelector` now preserves a singleton numeric object-map key as selector-only page identity when a `pages`, `dictionary_output`, or `pdftext` direct payload envelope contains exactly one direct layout/order payload and that payload has no explicit page marker.
- This makes singleton keyed direct payloads match the same selected-page boundary already used by multi-entry keyed maps. A stale singleton key for an unselected source page fails closed instead of losing the key and being assigned positionally.
- Internal `__markerpdf_envelope_page_key_marker` remains selector-only and is not copied into page layout/order metadata, WordPress output, or smoke comments.

## Red-First Evidence

Before the selector change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
...
FAIL rejects singleton source-key direct payload when key misses selected pdftext page
Expected source order; actual stale singleton-key order was applied.
FAIL rejects singleton keyed layout and order payload mismatches for WordPress imports
Expected layout_plan image_count 0; actual 1.
1 test files, 771 assertions, 2 failures
```

After the selector change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 794 assertions, 0 failures
```

The slice adds 2 focused PASS cases and 29 focused assertions.

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-singleton-key-mismatch-currentbase.php
```

The smoke exits 0 and emits `singleton_key_mismatch_rejected=true`, `layout_stage_skipped=true`, `order_stage_skipped=true`, `source_order_preserved=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted pdftext dictionary page slicing, selected-index matching, page number/page range marker handling, duplicate artifact rejection, mixed wrapper-list rejection, direct payload envelope unwrapping, keyed multi-entry source maps, keyed direct payload success with trusted outer page metadata, normalized/named/polygon/point-pair bbox handling, row-level page marker filtering, or JSON-decoded artifact normalization. The bounded behavior is only singleton numeric-keyed direct payload envelopes whose sole key belongs to an unselected source page.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, `PdfPageArtifactSelector`, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
