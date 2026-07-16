# markerPDF pdftext dictionary JSON-string envelope current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T070623Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T070623Z`
Accepted base: `b0c59c5f99abc8d96918caaa8798b022dda757b4`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `pdftext.extraction.dictionary_output(...)` inside `marker/pdf/extract_text.py::get_text_blocks()` before converting each page through `pdftext_format_to_blocks()`: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

The pdftext dependency documents `--json` output as a page-list JSON structure where each page contains `bbox`, `rotation`, `page`, and `blocks`, with nested lines/spans/font metadata: <https://github.com/datalab-to/pdftext>

Under the native no-GPU markerPDF scope, WordPress import adapters may reuse cached pdftext JSON output instead of running Python/pdftext/PDFium. Explicit `dictionary_output` or `pdftext` cache envelopes should therefore accept raw JSON page-list strings, but arbitrary span text strings must remain ordinary strings.

## Change

`PdfTextDocumentExtractor::pageListFromExplicitDictionaryEnvelope()` now decodes raw JSON strings only at the explicit `dictionary_output` / `pdftext` envelope boundary. Decoded arrays or JSON objects then flow through the existing recursive stdClass normalization, page-list ordering, core whitelist, link/ref sanitation, bbox/font validation, and selected-page slicing.

Malformed JSON strings still return `null` from the explicit-envelope parser and fail closed with the existing malformed-envelope exception before stale adapter pages can be imported. Direct page dictionaries with non-page string sidecars retain the accepted fallback behavior.

## Red-First Evidence

Before the source edit, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL unwraps raw JSON dictionary_output page strings before pdftext core conversion
Supplied pdftext dictionary_output envelope must contain a page dictionary or page list.
FAIL unwraps raw JSON pdftext page-list cache strings at the core boundary
Supplied pdftext cache envelope must contain a page dictionary or page list.
PASS rejects malformed raw JSON explicit pdftext strings before stale adapter pages

1 test files, 2 assertions, 2 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS unwraps raw JSON dictionary_output page strings before pdftext core conversion
PASS unwraps raw JSON pdftext page-list cache strings at the core boundary
PASS rejects malformed raw JSON explicit pdftext strings before stale adapter pages

1 test files, 25 assertions, 0 failures
```

Adjacent pdftext dictionary boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
2 test files, 750 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-json-string-envelope-currentbase.php --self-test
```

The smoke exits 0 and reports `raw_json_cache_unwrapped=true`, `safe_span_link_promoted=true`, `reference_anchor_synthesized=true`, `stale_adapter_excluded=true`, `cover_appendix_excluded=true`, `raw_payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted JSON-decoded stdClass pdftext pages, object-valued page maps, named `dictionary_output` envelopes, `pdftext` cache envelopes, direct page dictionaries, singleton/nested page envelopes, selected blank pages, sort/order alignment, keep-chars sanitation, link/ref preservation, disabled-link handling, empty-span handling, source dimension/bbox normalization, Unicode repair, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only raw JSON strings under explicit pdftext dictionary cache envelopes.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary boundary, JSON decoder, stdClass normalizer, page-list selector, block converter, sanitizer, Markdown postprocessor, and WordPress smoke path. Live pdftext/PDFium, OCR, Surya/Texify/Torch model execution, PIL/raster rendering, Streamlit/FastAPI workers, online services, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
