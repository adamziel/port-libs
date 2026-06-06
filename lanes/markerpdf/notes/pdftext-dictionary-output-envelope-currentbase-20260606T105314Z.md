# markerPDF pdftext dictionary_output envelope core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T105314Z`

Session: `port-dev-markerpdf-pdftext-dictionary-20260606T105314Z`

Base accepted HEAD: `5981f5c45290b13799261fadb4def4415f4483ff`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)`, then enumerates the returned page dictionaries before converting each page into Marker `Page` and `Span` structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` dictionary output returns an ordered page list and strips block/line/span dictionaries to the trusted output shape before markerPDF conversion: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor` now treats a top-level `dictionary_output` key as a cached native-adapter envelope and unwraps its nested `pages` list before selected-page slicing.
- JSON-decoded `stdClass` envelopes are normalized at the boundary, including object-valued nested page maps keyed by original pdftext page numbers.
- Each unwrapped page still goes through the existing recursive object normalization, page sanitizer, safe URI/ref handling, text normalization, bbox scaling, and converter validation.
- Envelope-level metadata, adapter payloads, skipped pages, and stale nested `dictionary_output` payload strings are not copied into Marker pages, `pdftext_source`, `char_blocks`, or visible WordPress output.
- Added a WordPress smoke showing selected-page import, source-page count preservation, synthesized page refs, safe span-link promotion, skipped-page exclusion, and no Python/pdftext/model/external-tool execution.

## Verification

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL unwraps named dictionary_output page envelopes at the core boundary
pdftext bbox must be a four-number bbox.
1 test files, 242 assertions, 1 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 255 assertions, 0 failures
```

Additional focused verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 600 assertions, 0 failures

php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-output-envelope-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-output-envelope-currentbase.php

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-output-envelope-currentbase.php
emits dictionary_output_envelope_unwrapped=true, nested_pages_envelope_unwrapped=true, selected_pdftext_page=81, source_pages=3, page_ref_synthesized=true, safe_span_link_promoted=true, dictionary_output_payload_excluded=true, skipped_pages_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.

git diff --check -- lanes/markerpdf
passed
```

Focused delta: +1 focused PASS case and +13 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`; +1 WordPress smoke example. `lane-status.json` `phpPass` moves `2517 -> 2518`, `wordpressScenarios` moves `2139 -> 2140`, and mapped denominator is unchanged.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP pdftext dictionary conversion, object normalization, dictionary-output sanitation, safe URI/ref handling, Markdown post-processing, and the existing WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted plain `pages` envelopes, default JSON-decoded page objects, link/ref preservation, disable-links behavior, keep-chars validation, char-index validation, Unicode repair, bbox normalization, sorting, blank-page handling, layout/order artifact routing, parser/xref repair, CMap/font/width behavior, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary work. The bounded behavior is only named `dictionary_output` adapter/cache envelope unwrapping before native selected-page conversion.
