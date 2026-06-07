# markerPDF pdftext dictionary layout/order source-key map boundary current base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260607T112253Z`
Base: `593e250de39f81742afff1251b9212cc0fdee33e`

## Source truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` trims searchable-PDF pages through `marker/pdf/extract_text.py::get_text_blocks()` / `pdftext.extraction.dictionary_output(..., page_range=...)` before Marker page conversion.
- Upstream layout/order handoff zips one layout/order prediction with each selected Marker page. Native no-GPU adapters that cache supplied artifacts as pdftext-shaped object maps must therefore align by trusted selected source-page identity before zip-style assignment.
- This remains in the no-GPU markerPDF scope: it does not execute OCR, Surya/Texify/Torch, pypdfium/PDFium, PDF actions, or external PDF tools.

## Implementation

- `PdfPageArtifactSelector` now preserves numeric keys from `pages`, `dictionary_output`, and `pdftext` artifact object maps as an internal selector-only page marker.
- The marker can match either selected source index, the selected pdftext page number, or the one-based page number form, then is discarded by layout/order sanitizers.
- Stale keyed payloads and raw adapter fields remain outside Marker page/order/layout metadata and WordPress text.

## Red-first evidence

Before the fix, this inline boundary check failed with exit code `7`:

```text
{"texts":["Second selected","First selected"],"assigned":1,"count":1}
```

The fixture supplied a `dictionary_output` order map keyed `5401` then `5400`, selected `start_page=1`, and expected page `5401`. The old normalizer flattened the map by insertion order, then positional slicing selected the stale second row.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS uses source-page keyed dictionary-output maps before selected pdftext order assignment
1 test files, 765 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-source-key-map-currentbase.php
exits 0; emits layout_key_map_selected=true, order_key_map_selected=true, heading_before_body=true, cover_excluded=true, payload_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-source-key-map-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency closure

No new support component is needed. This reuses the existing native PHP supplied-artifact selector, layout annotator, layout orderer, and supplied document converter. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF lane directive.

## Non-overlap

This does not repeat prior pdftext dictionary page envelopes, selected page-range slicing, direct single keyed payload envelopes, page/page_idx/pdftext_source marker aliases, wrapper-list rejection, duplicate artifact rejection, non-finite/zero-area bbox guards, row-level page-marker filtering, CMap/font/xref/image/filter/parser behavior, annotations/forms/security, table/equation supplied boundaries, OCR, or model parity. The bounded behavior is only multi-entry pdftext-shaped layout/order artifact object maps whose numeric keys provide the selected source-page identity.
