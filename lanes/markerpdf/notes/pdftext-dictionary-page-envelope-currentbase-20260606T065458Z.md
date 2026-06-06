# markerPDF pdftext dictionary page-envelope core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T065458Z`

Base accepted HEAD: `3efeafdae2b4cfdef1fbe9e4754af21030851d2d`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)`, then enumerates the returned page list before converting each page dictionary into Marker `Page`/`Span` structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `pdftext.extraction.dictionary_output(...)` returns an ordered `Pages` list and strips block/line/span payloads to the trusted dictionary-output shape before returning page dictionaries: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor` now unwraps a cached supplied dictionary envelope with a `pages` list before applying `start_page`/`max_pages` slicing.
- Envelope-level metadata and adapter payloads are not copied into Marker pages, `pdftext_source`, `char_blocks`, or visible WordPress output.
- The existing page sanitizer remains authoritative for each unwrapped page, so span links, synthesized pdftext refs, page source geometry, and text normalization keep the current core-boundary behavior.
- Added a WordPress smoke for cached page-envelope imports with safe span-link promotion, synthesized internal refs, selected-page slicing, skipped-page exclusion, and no Python/model/external-tool execution.

## Verification

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL unwraps cached pdftext dictionary page envelopes at the core boundary
pdftext bbox must be a four-number bbox.
1 test files, 210 assertions, 1 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 222 assertions, 0 failures
```

Additional focused verification:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-envelope-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-envelope-currentbase.php

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionary*CurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
3 test files, 903 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-envelope-currentbase.php
emits selected_pdftext_page=31, page_ref_synthesized=true, safe_span_link_promoted=true, envelope_payload_excluded=true, skipped_pages_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.

git diff --check -- lanes/markerpdf
passed
```

Focused delta: +1 focused PASS case and +12 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`; +1 WordPress smoke example.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP pdftext dictionary conversion, dictionary-output sanitation, safe URI/ref handling, Markdown post-processing, and the existing WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat pdftext link/ref preservation, page payload pruning, keep-chars validation, char-index validation, Unicode repair, bbox normalization, sorting, blank-page handling, layout/order artifact routing, parser/xref repair, CMap/font/width behavior, image/filter metadata, annotations/forms/security, or table/equation supplied-boundary work. The bounded behavior is only cached dictionary-output page-envelope unwrapping before native core page conversion.
