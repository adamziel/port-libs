# markerPDF pdftext dictionary layout/order page-key boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T203726Z`
Base accepted HEAD: `0864f62253e0e164cd7935b30a381c071acdbd24`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` consumes page dictionaries from `pdftext.extraction.dictionary_output(...)`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before layout/order images are rendered, then layout/order outputs are assigned one prediction per selected pdftext page.
- Native source-page keyed layout/order sidecars are therefore page identity maps. Keys must be non-negative source-page indexes and must fit in a PHP integer before a sidecar can be aligned to selected WordPress import pages.

## Implementation

- `PdfPageArtifactSelector::integerArrayKey()` now rejects negative source-page artifact map keys and keys larger than `PHP_INT_MAX`.
- Direct keyed artifact maps, keyed pdftext-style envelopes, single keyed payload envelopes, and direct payload envelope key collection now parse keys only after the candidate is artifact-shaped, so inert metadata arrays remain review-only.
- Valid zero-valued layout/order map keys, including `-0.0`, still import and assign the selected page, while overflow or genuinely negative sibling keys fail before stale layout/order payloads can be assigned.

## Evidence

Red-first before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects overflow source-keyed order artifact maps before selected pdftext assignment
Expected exception InvalidArgumentException was not thrown
FAIL rejects negative layout and order artifact map keys while zero source keys still import
Expected exception InvalidArgumentException was not thrown

1 test files, 2 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overflow source-keyed order artifact maps before selected pdftext assignment
PASS rejects negative layout and order artifact map keys while zero source keys still import

1 test files, 13 assertions, 0 failures
```

Adjacent layout/order family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfTextDictionaryLayoutOrder.*CurrentBaseTest\.php' | sort)
Focused test run: 27 selected test files (root lock skipped)
27 test files, 1689 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-key-boundary-currentbase.php --self-test
```

The smoke exits 0 and emits `overflow_order_key_rejected=true`, `negative_layout_key_rejected=true`, `negative_order_key_rejected=true`, `zero_keyed_layout_imported=true`, `zero_keyed_order_imported=true`, `heading_before_body=true`, `payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary normalization, supplied artifact selector, layout/order assignment, supplied document converter, and WordPress smoke path. No Python, pdftext runtime, pypdfium, Surya layout/order models, OCR, Texify, Torch/model execution, Streamlit/FastAPI worker, live service, or external PDF tool was run.

## Non-overlap

This does not repeat accepted duplicate normalized artifact keys, decimal/direct source-key maps, page-map envelopes, raw JSON keyed values, JSON list entries, envelope key aliases, direct option envelopes, raw page artifact rejection, metadata sibling exclusion, row-level stale marker filtering, normalized/named/polygon geometry, non-finite/zero-area bbox guards, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only invalid negative or overflowing source-page keys inside supplied layout/order artifact maps.

Root harness: not run - isolated micro-slice.
