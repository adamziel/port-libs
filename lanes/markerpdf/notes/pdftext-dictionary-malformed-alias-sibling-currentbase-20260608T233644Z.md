# markerPDF pdftext dictionary malformed alias sibling current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T233644Z`

Accepted base: `9ded36a0bdf8a38d0d938423ba129d62e7355cba`

## Source Truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `marker/pdf/extract_text.py::get_text_blocks()` to call `pdftext.extraction.dictionary_output(...)` for the selected `page_range`, then enumerates that returned page list into `pdftext_format_to_blocks()`.

The native PHP adapter accepts cache aliases (`dictionary_output`, `pdftext`, `pages`, `page_map`, and `pageMap`) around that single ordered page list. If an explicit `dictionary_output` or `pdftext` cache contains a known page-list alias that is malformed, that cache is corrupt. It should fail closed before importing a later alias sibling or stale adapter `pages` rows into WordPress paragraphs.

## Patch

- `PdfTextDocumentExtractor::pageListFromExplicitDictionaryEnvelope()` now returns a malformed explicit envelope as soon as a present `pages`, `page_map`, or `pageMap` alias is not a valid page list.
- Valid alias-only caches still import when no malformed earlier alias is present.
- Added a focused core-boundary test and a WordPress smoke for:
  - malformed `pages` before valid `page_map` inside `dictionary_output`;
  - malformed `page_map` before valid `pageMap` inside raw JSON `pdftext`;
  - valid `pageMap` without malformed siblings.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedAliasSiblingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed pages alias before valid page_map sibling in explicit dictionary_output envelopes
FAIL rejects malformed page_map alias before valid pageMap sibling in raw pdftext JSON envelopes
PASS keeps valid later page-map aliases importable when earlier malformed aliases are absent
1 test files, 12 assertions, 2 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedAliasSiblingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed pages alias before valid page_map sibling in explicit dictionary_output envelopes
PASS rejects malformed page_map alias before valid pageMap sibling in raw pdftext JSON envelopes
PASS keeps valid later page-map aliases importable when earlier malformed aliases are absent
1 test files, 12 assertions, 0 failures
```

Dictionary-core family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCore*CurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryPageMapDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryRefDuplicateCoordinateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionarySourceBboxScaleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 791 assertions, 0 failures
```

Lint:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedAliasSiblingBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-malformed-alias-sibling-currentbase.php
```

All reported no syntax errors.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-malformed-alias-sibling-currentbase.php
```

Exited 0 with `malformed_dictionary_output_pages_before_page_map_rejected=true`, `malformed_pdftext_page_map_before_pageMap_rejected=true`, `valid_alias_without_malformed_sibling_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted raw JSON/BOM envelope decoding, direct and nested explicit envelope precedence, valid `page_map`/`pageMap` alias unwrapping, malformed page-list key rejection before numeric sibling rows, duplicate/negative/overflow page-map key checks, pageMap layout/order key precedence, JSON list-entry decoding, source bbox scaling, ref deduplication, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, runtime preflight, OCR/model paths, or table/equation supplied-boundary work.

The bounded behavior is only malformed known page-list alias siblings inside explicit `dictionary_output`/`pdftext` caches.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary core boundary, JSON envelope decoder, page-list normalizer, Markdown/WordPress smoke path, and focused PHP tests. Live `pdftext`, PDFium/pypdfium, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
