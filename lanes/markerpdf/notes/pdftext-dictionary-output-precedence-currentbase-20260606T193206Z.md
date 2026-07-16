# markerPDF pdftext dictionary_output precedence current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T193206Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260606T193206Z`
Accepted base: `25ea07f71d9d374a0547131630b25b485b558f60`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` inside `marker/pdf/extract_text.py::get_text_blocks()` and then converts the returned page dictionaries with `pdftext_format_to_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked pdftext `v0.3.18` `dictionary_output(...)` returns the authoritative ordered page list after page-range selection, block/line key trimming, bbox unnormalization, text post-processing, optional character preservation, and optional sorting: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextDocumentExtractor::normalizeSuppliedDictionaryPageList()` now tries an explicit top-level `dictionary_output` payload before generic top-level `pages`.

This keeps adapter envelopes that contain stale CMS/preview `pages` metadata from overriding the actual cached pdftext dictionary output. The selected pdftext page range, source page number, safe span link promotion, synthesized reference anchors, and raw payload exclusion all remain on the explicit `dictionary_output` page list.

Added `examples/wordpress-pdftext-dictionary-output-precedence-currentbase.php` to model the WordPress import path. It renders the explicit dictionary output page, excludes stale adapter page text and private payloads, promotes a safe span link, synthesizes a pdftext reference anchor, and records that no Python pdftext, models, OCR, PDFium, or external PDF tools execute.

## Red/Green Evidence

Red-first focused check after adding the new tests and before the source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 291 assertions, 2 failures`. The failing selected page numbers were stale top-level adapter pages: expected `501` but got `401`, and expected `710` but got `610`.

Focused check after implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 305 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-output-precedence-currentbase.php
```

Result: emitted `explicit_dictionary_output_selected=true`, `selected_pdftext_page=90`, `stale_adapter_pages_excluded=true`, `adapter_payload_excluded=true`, `safe_span_link_promoted=true`, `pdftext_ref_anchor_synthesized=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Additional focused family check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `3 test files, 652 assertions, 0 failures`.

Syntax and whitespace checks:

```sh
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php && php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-output-precedence-currentbase.php
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: PHP lint clean for changed PHP files, `lane-status json ok`, and no diff whitespace errors.

## Non-Overlap

This does not repeat direct page wrapping, pages-only envelope unwrapping, named dictionary_output unwrapping without a top-level pages collision, JSON object normalization, selected blank pages, sorting, quote_loosebox metadata, keep_chars sanitation, span/ref/link validation, empty-span dropping, text Unicode repair, bbox scaling, layout/order artifact matching, parser/xref repair, font/CMap/native PDF extraction, image/filter metadata, annotations/forms/security preflight, runtime preflight, or supplied table/equation/OCR boundaries. The bounded behavior is only explicit `dictionary_output` precedence when stale top-level adapter `pages` are also present.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary converter, envelope normalizer, Markdown/WordPress smoke path, and focused PHP tests. Live pdftext/PDFium/pypdfium, Surya/Texify/Torch model execution, OCR/table/equation model workers, Streamlit/FastAPI paths, and external PDF tools remain intentionally out of scope for the no-GPU markerPDF lane.
