# markerPDF pdftext dictionary negative page-map key boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T185111Z`

Session: `port-dev-markerpdf-pdftext-dictionary-20260608T185111Z`

Base accepted HEAD: `b447d62fe2d4d5d0f90480f2b02ce0d549f8fa65`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` over a selected `page_range`, then enumerates the returned ordered page dictionaries before converting them into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` v0.3.18 builds page dictionaries from PDFium page indexes and returns the selected ordered list from `dictionary_output(...)`; native adapter object maps keyed by source page must therefore use zero-or-greater page identities before sorting and slicing: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

- `PdfTextDocumentExtractor` now rejects page-shaped native pdftext dictionary maps whose normalized source-page key is negative.
- Zero remains a valid source-page key, and positive decimal/plus aliases continue through the existing normalized-key ordering and duplicate-key guard.
- Negative-key pages fail before stale adapter siblings can be selected, so their text and payload metadata cannot enter WordPress paragraphs, `char_blocks`, or `pdftext_source`.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNegativePageMapKeyBoundaryCurrentBaseTest.php
FAIL rejects negative dictionary_output page-map keys before stale adapter pages
Expected exception InvalidArgumentException was not thrown
FAIL rejects negative raw JSON pdftext page-map keys before stale adapter pages
Expected exception InvalidArgumentException was not thrown
PASS keeps zero source-page page-map keys importable at the core boundary
1 test files, 12 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNegativePageMapKeyBoundaryCurrentBaseTest.php
1 test files, 12 assertions, 0 failures
```

Adjacent pdftext dictionary core check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryPageMapDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedPageListKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedMalformedEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
7 test files, 845 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-negative-page-map-key-currentbase.php
```

The smoke exits 0 and emits `negative_dictionary_output_key_rejected=true`, `negative_json_pdftext_key_rejected=true`, `zero_source_page_key_imported=true`, `payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +3 focused PASS cases and +12 focused assertions; +1 WordPress smoke scenario. Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted dictionary_output envelope unwrapping, malformed page-list key rejection, raw JSON string envelope decoding, JSON-decoded `stdClass` pages, direct page dictionaries, singleton/nested/list-entry envelopes, duplicate normalized page-key guards, decimal-key ordering for valid maps, selected blank pages, sorting, quote-loosebox metadata, keep-chars sanitation, link/ref preservation, disabled-link handling, empty-span handling, source dimension/bbox normalization, Unicode repair, layout/order artifact routing, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security preflight, runtime preflight, table recognition, OCR, or equation/image supplied-boundary work.

The bounded behavior is only fail-closed validation for negative normalized source-page keys in native pdftext dictionary page maps.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary page-list normalizer, JSON/object envelope normalization, selected page slicing, page sanitizer, Markdown/WordPress smoke renderer, and focused PHP test harness. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF lane rule.
