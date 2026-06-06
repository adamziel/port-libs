# markerPDF pdftext dictionary pdftext-envelope core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T200626Z`

Session: `port-dev-markerpdf-pdftext-dictionary-20260606T200626Z`

Base accepted HEAD: `76840eb7eed0b0be3b0bd00d44d593fe95f8ba18`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)`, enumerates the returned page dictionaries, and converts each page through `pdftext_format_to_blocks(...)`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` `dictionary_output(...)` returns an ordered page list after scaling block/line/span bboxes and stripping block/line payload keys: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor` now unwraps a native cache envelope named `pdftext` before falling back to generic adapter `pages`.
- Existing precedence is preserved: explicit `dictionary_output` remains authoritative first, then `pdftext`, then legacy `pages`.
- JSON-decoded `stdClass` envelopes with object-valued nested page maps are normalized before selected-page slicing.
- Stale top-level adapter pages and private envelope payloads are excluded from Marker pages, `char_blocks`, `pdftext_source`, and WordPress text.

## Red-First Evidence

After adding the focused cases and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
=> 1 test files / 308 assertions / 2 failures
```

Failures:

- `prefers explicit pdftext envelope over stale adapter pages at the core boundary` selected stale page `810` instead of authoritative pdftext page `910`.
- `unwraps json decoded pdftext page envelopes at the core boundary` treated the JSON envelope metadata as a page and failed with `pdftext bbox must be a four-number bbox.`

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
=> 1 test files / 324 assertions / 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
=> 3 test files / 1420 assertions / 0 failures

php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
=> No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
=> No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-pdftext-envelope-currentbase.php
=> No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-pdftext-envelope-currentbase.php

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-pdftext-envelope-currentbase.php
=> emitted pdftext_envelope_selected=true, safe_span_link_promoted=true, payload_excluded=true, executes_python_or_models=false, executes_ocr=false, executes_external_pdf_tools=false
```

Focused delta: +2 focused PASS cases, +16 assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`, and +1 WordPress smoke. `lane-status.json` `phpPass` moves `2667 -> 2669`; `wordpressScenarios` moves `2250 -> 2251`; mapped manifest denominator is unchanged.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary converter, recursive object normalization, dictionary-output sanitation, safe URI/ref handling, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat direct page dictionaries, top-level `pages` envelopes, explicit `dictionary_output` envelopes, JSON-decoded page dictionaries, link/ref preservation, disable-links handling, keep-chars validation, character-index validation, Unicode repair, bbox normalization, sort handling, blank pages, layout/order artifact routing, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary behavior. The bounded new behavior is only a native adapter/cache envelope named `pdftext` that contains the authoritative `dictionary_output` page list.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
