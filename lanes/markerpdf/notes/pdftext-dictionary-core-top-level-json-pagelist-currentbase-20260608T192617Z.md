# PDFText Dictionary Core Top-Level JSON Page-List Current Base

Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T192617Z`

Base: `7d20a7f38b825cc1219ba92c295eed6a42a4e953`

## Source Truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py`, slices by `page_range`, then converts each returned page dictionary through `pdftext_format_to_blocks`.

Source: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`

This native PHP boundary covers WordPress/native adapter caches that store that ordered pdftext page-list as a raw JSON string under a top-level legacy `pages`, `page_map`, or `pageMap` key. Explicit `dictionary_output`/`pdftext` envelopes already decoded raw JSON strings; the top-level legacy page-list path should use the same bounded JSON-envelope decoder before selected-page slicing.

No OCR, Surya/Texify/Torch, PDFium rendering, Python pdftext execution, or external PDF tools were run.

## Red Probe

Before the source edit, a focused current-base probe with `getTextBlocks(['pages' => $rawJsonPageMap], maxPages: 1)` failed before selected page conversion:

```text
InvalidArgumentException: pdftext bbox must be a four-number bbox.
```

The raw JSON string was being treated as a single page entry instead of being decoded into the page map.

## Patch

- `PdfTextDocumentExtractor::normalizeSuppliedDictionaryPageList()` now normalizes top-level `pages`, `page_map`, and `pageMap` values through `normalizeSuppliedDictionaryEnvelopeValue()`.
- Added focused coverage for BOM-prefixed top-level raw JSON `pages` and raw JSON `page_map`/`pageMap` aliases.
- Added a WordPress smoke proving selected-page text, safe link promotion, pdftext refs, stale page exclusion, and wrapper payload exclusion.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreTopLevelJsonPageListBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 PASS cases; 1 test file, 40 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCore*CurrentBaseTest.php
10 test files, 662 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionary*CurrentBaseTest.php
40 test files, 2396 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
1 test file, 300 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-top-level-json-pagelist-currentbase.php
```

The smoke reports `top_level_json_pages_selected=true`, `selected_page_range_preserved=true`, `source_page_count_preserved=true`, `safe_pdftext_link_promoted=true`, `pdftext_ref_preserved=true`, `stale_pages_excluded=true`, `wrapper_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreTopLevelJsonPageListBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-top-level-json-pagelist-currentbase.php
```

All changed PHP files reported no syntax errors.

## Non-Overlap

This does not repeat accepted nested explicit `dictionary_output`/`pdftext` envelopes, raw JSON pages members inside explicit envelopes, raw JSON selected page-list entries, malformed page-list key rejection, negative page-map key rejection, layout/order direct option envelopes, page-map layout/order envelopes, or supplied artifact matching. The new boundary is only top-level legacy `pages`/`page_map`/`pageMap` keys whose value is a raw JSON page-list string.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary normalizer, bounded JSON envelope decoder, selected page slicing, block converter, Markdown merge path, focused PHP test harness, and WordPress smoke renderer. Full upstream pdftext/PDFium/model runner parity remains intentionally out of scope under the no-GPU markerPDF directive.

Root harness: not run - isolated micro-slice.
