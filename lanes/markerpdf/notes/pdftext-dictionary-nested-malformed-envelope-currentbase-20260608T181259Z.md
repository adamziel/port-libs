# markerPDF pdftext dictionary nested malformed envelope current-base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T181259Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T181259Z`
Accepted base: `830c9a682bd827bfdd2817a678f3fc18d9745b5a`

## Source truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks`, then enumerates the returned page dictionaries before Marker block conversion. Native no-GPU adapters may cache `dictionary_output` or `pdftext` JSON/envelope payloads; once an explicit nested cache key is present, that cache boundary must be valid and must not fall through to stale sibling adapter pages.

## Behavior

`PdfTextDocumentExtractor::pageListFromExplicitDictionaryEnvelope()` now treats malformed nested `dictionary_output` or `pdftext` keys as an invalid explicit envelope. Before this patch, an outer cache with valid sibling `pages` plus malformed nested `dictionary_output`/`pdftext` would silently import the sibling pages. After this patch, the explicit cache fails closed with `InvalidArgumentException`, preserving the accepted behavior where direct page dictionaries with top-level `blocks` remain authoritative even when they carry non-page sidecars.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedMalformedEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed nested dictionary_output caches before sibling adapter pages
Expected exception InvalidArgumentException was not thrown
FAIL rejects malformed nested pdftext JSON caches before sibling adapter pages
Expected exception InvalidArgumentException was not thrown

1 test files, 2 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedMalformedEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed nested dictionary_output caches before sibling adapter pages
PASS rejects malformed nested pdftext JSON caches before sibling adapter pages

1 test files, 2 assertions, 0 failures
```

Adjacent pdftext dictionary core family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfTextDictionaryCore*Test.php' | sort) lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryPageMapDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
...
10 test files, 637 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php && php -l lanes/markerpdf/tests/PdfTextDictionaryCoreNestedMalformedEnvelopeBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-nested-malformed-envelope-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreNestedMalformedEnvelopeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-nested-malformed-envelope-currentbase.php

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-nested-malformed-envelope-currentbase.php
Result: exits 0 and reports nested_dictionary_output_rejected=true, nested_pdftext_json_rejected=true, stale_sibling_pages_imported=false, executes_python_pdftext=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted direct page wrapping, explicit top-level malformed wrapper rejection, malformed `pages`/`page_map` key rejection, nested valid explicit envelope precedence, raw JSON/BOM envelope decoding, page-map duplicate key rejection, layout/order artifact matching, parser/xref repair, font/CMap/width behavior, image/filter metadata, annotations/forms/security, table/equation supplied boundaries, OCR, or model parity. The bounded behavior is only malformed nested explicit pdftext cache keys inside otherwise page-shaped adapter envelopes.

## Dependency closure

No new support component is needed. This reuses the native PHP pdftext dictionary extractor, existing JSON/envelope normalization, block converter, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/Torch/OCR/layout/order/table-cell models, Texify, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
