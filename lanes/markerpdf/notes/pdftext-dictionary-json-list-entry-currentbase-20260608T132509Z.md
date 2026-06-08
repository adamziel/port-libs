# markerPDF pdftext dictionary JSON list-entry current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T132509Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T132509Z`
Accepted base: `f2c68bcb90cae7f8d5c420ad4c2ba78bf326142c`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks()`, then enumerates those selected page dictionaries into Marker page/block/span objects before WordPress-oriented conversion.

Locked pdftext `v0.3.18` `dictionary_output(...)` returns an ordered page list after page-range selection, block/line key trimming, span text post-processing, optional character preservation, bbox scaling, and optional sorting. Native WordPress import adapters can cache those ordered page dictionaries as JSONL-style records without rerunning Python/pdftext/PDFium.

## Change

`PdfTextDocumentExtractor::unwrapSuppliedDictionaryPageEntry()` now decodes raw JSON object/list strings only at the page-list entry boundary. The decoded entry then flows through the existing exact-one-page envelope unwrap, recursive `stdClass` normalization, selected-page slicing, core key whitelist, link/ref sanitation, bbox/font validation, and Markdown conversion.

This keeps arbitrary span text and private string payloads inert: JSON decoding is not applied during recursive page sanitation, only when the value is already being treated as a supplied pdftext page entry.

## Red-First Evidence

Before the source edit, after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonListEntryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL unwraps raw JSON pdftext page strings inside selected page-list entries at the core boundary
Supplied pdftext page entries must be arrays.
FAIL unwraps raw JSON one-page pdftext envelopes inside selected page-list entries
Supplied pdftext page entries must be arrays.

1 test files, 0 assertions, 2 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonListEntryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS unwraps raw JSON pdftext page strings inside selected page-list entries at the core boundary
PASS unwraps raw JSON one-page pdftext envelopes inside selected page-list entries

1 test files, 26 assertions, 0 failures
```

Adjacent core boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 450 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-json-list-entry-currentbase.php
```

The smoke exits 0 and reports `json_list_entry_unwrapped=true`, `safe_span_link_promoted=true`, `reference_anchor_synthesized=true`, `stale_wrapper_excluded=true`, `cover_appendix_excluded=true`, `raw_payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted raw JSON explicit `dictionary_output`/`pdftext` envelopes, JSON-decoded `stdClass` page objects, object-valued page maps, direct page dictionaries, singleton/nested page envelopes, duplicate normalized page-key guards, selected blank pages, sorting, quote-loosebox metadata, keep-chars sanitation, link/ref preservation, disabled-link handling, empty-span handling, source dimension/bbox normalization, Unicode repair, layout/order artifact routing, parser/xref repair, font/CMap/native PDF extraction, image/filter metadata, annotations/forms/security preflight, runtime preflight, or supplied table/equation/OCR boundaries.

The bounded behavior is only raw JSON strings that appear as page-list entries in the core pdftext dictionary boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary page-list selector, JSON decoder, `stdClass` normalizer, one-page envelope unwrap, core sanitizer, `PdfTextBlockConverter`, Markdown postprocessor, and WordPress smoke harness. Live pdftext/PDFium, OCR, Surya/Texify/Torch model execution, PIL/raster rendering, Streamlit/FastAPI workers, online services, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
