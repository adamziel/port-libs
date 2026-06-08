# markerPDF pdftext dictionary page-map key overflow boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T201447Z`

Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Source Truth

- Pinned upstream markerPDF consumes the ordered page dictionaries returned by `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks()` before Marker page/block/span conversion.
- Native WordPress adapters may preserve that ordered output as source-page keyed `dictionary_output` / `pdftext` page maps. Those keys are source-page identities and must be bounded integer values before selected-page slicing.

## Implemented Behavior

- `PdfTextDocumentExtractor::integerArrayKey()` now rejects integer-like string keys whose digit value exceeds `PHP_INT_MAX` before casting.
- Valid max-sized integer keys remain importable, zero and negative-key behavior remains covered by the existing boundary, and nonnumeric adapter fallback behavior is unchanged.
- Overflow keys in explicit `dictionary_output` arrays and raw JSON `pdftext.page_map` caches fail closed before stale adapter `pages` or sibling rows can become WordPress-visible text.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapKeyOverflowBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects overflow dictionary_output page-map keys before stale adapter pages
Expected exception InvalidArgumentException was not thrown
FAIL rejects overflow raw JSON pdftext page-map keys before stale adapter pages
Expected exception InvalidArgumentException was not thrown
PASS keeps maximum integer source-page page-map keys importable at the core boundary

1 test files, 13 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCorePageMapKeyOverflowBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCorePageMapKeyOverflowBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-key-overflow-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-key-overflow-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapKeyOverflowBoundaryCurrentBaseTest.php
1 test files, 13 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapKeyOverflowBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreNegativePageMapKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedPageListKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreTopLevelJsonPageListBoundaryCurrentBaseTest.php
5 test files, 109 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCore*.php
11 test files, 675 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-key-overflow-currentbase.php
exits 0 with overflow_dictionary_output_key_rejected=true, overflow_json_pdftext_key_rejected=true, max_integer_source_key_imported=true, safe_span_link_promoted=true, reference_anchor_synthesized=true, payload_excluded=true, executes_python_pdftext=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Focused delta: +3 PASS cases, +13 focused assertions, and +1 WordPress smoke scenario.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary core boundary, explicit envelope handling, JSON envelope decoding, page-map ordering, safe URI/ref sanitation, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted negative page-map key rejection, malformed page-list key rejection, duplicate normalized page-map keys, page_map/pageMap envelope unwrapping, top-level JSON pages/page_map/pageMap decoding, nested explicit envelopes, singleton page envelopes, link/ref preservation, keep-chars sanitation, bbox normalization, layout/order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary behavior. The bounded behavior is only overflow-sized integer-like source-page keys in native pdftext dictionary page maps.
