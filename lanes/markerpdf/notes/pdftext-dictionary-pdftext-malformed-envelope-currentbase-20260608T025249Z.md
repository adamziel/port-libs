# markerPDF pdftext dictionary malformed pdftext envelope current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T025249Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T025249Z`
Base accepted HEAD: `02ca21f0a770f96178de4e85f83f87d2bf977c2c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` for the selected page range and then converts only those returned page dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` dictionary output returns a page-list handoff and strips block/line payload keys before Marker conversion. Native PHP cache envelopes named `pdftext` therefore need to be page-shaped; malformed explicit cache payloads must not fall back to stale adapter `pages`: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Implementation

- `PdfTextDocumentExtractor::explicitSuppliedDictionaryPageList()` now rejects malformed explicit `pdftext` cache envelopes when the outer value is not already a direct page dictionary with `blocks`.
- This mirrors the existing explicit `dictionary_output` fail-closed boundary and keeps stale adapter `pages` from being imported when an authoritative pdftext cache field is present but scalar/null/malformed.
- Direct page dictionaries remain authoritative even if they carry non-page `dictionary_output` or `pdftext` sidecar metadata; those sidecars stay out of sanitized WordPress output.
- Added a WordPress smoke showing malformed scalar/null `pdftext` wrappers are rejected while a direct page with a non-page `pdftext` sidecar remains importable and excludes the sidecar payload.

## Red First

Before the source change, with the new malformed-`pdftext` wrapper test in place:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL rejects malformed explicit pdftext wrappers before stale adapter pages at the core boundary
Expected exception InvalidArgumentException was not thrown
1 test files, 444 assertions, 1 failures
```

The failure showed malformed explicit `pdftext` cache payloads falling back to stale adapter `pages`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 450 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-pdftext-malformed-envelope-currentbase.php
```

The smoke emitted `malformed_pdftext_scalar_rejected=true`, `malformed_pdftext_null_rejected=true`, `stale_adapter_pages_excluded=true`, `direct_page_with_pdftext_sidecar_imported=true`, `direct_pdftext_sidecar_excluded=true`, `safe_span_link_promoted=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, `executes_ocr=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` and +1 WordPress smoke.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary converter, cache-envelope normalizer, supplied-document converter, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat accepted page-range slicing, direct page wrapping, `dictionary_output` malformed-envelope rejection, valid `pdftext` envelope precedence, singleton/nested/list-entry envelope unwrapping, JSON object normalization, link/ref preservation, disable-links behavior, keep-chars validation, font flags, character indexes, Unicode repair, normalized bbox scaling, layout/order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations, forms, security preflight, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is malformed explicit `pdftext` cache envelopes failing closed before stale adapter pages.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter boundaries around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
