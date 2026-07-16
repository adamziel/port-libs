# pdftext Dictionary Page Map Envelope Boundary

Session: `port-dev-markerpdf-pdftext-dictionary-20260608T162202Z`
Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T162202Z`
Accepted base: `bc9489e331853d7b5b38ea37ea420a29310b5ae4`

## Source Truth

`pdftext.dictionary_output(...)` returns structured page dictionaries in page order, and markerPDF consumes pages/blocks from that no-GPU text layer before downstream rendering. Native WordPress/import caches may key those already-ordered page dictionaries by source page in `page_map`/`pageMap` wrappers, but those wrappers must be unwrapped before stale adapter `pages` or wrapper text can reach Gutenberg paragraphs.

Primary source consulted: https://github.com/datalab-to/pdftext#json

## Behavior

- `PdfTextDocumentExtractor` now treats `pages`, `page_map`, and `pageMap` as equivalent nested page-list aliases inside explicit `dictionary_output` and `pdftext` envelopes.
- Selected page-list entries with a one-page `page_map`/`pageMap` cache unwrap to the current pdftext page instead of stale wrapper `blocks`.
- Safe span links and synthesized pdftext reference anchors are preserved after unwrapping.
- Wrapper metadata, stale cover/appendix pages, and raw page/block/line/span/ref payloads remain excluded from serialized document output and WordPress paragraphs.
- No Python pdftext, pypdfium, OCR, CUDA/Torch, raster rendering, model worker, or external PDF tool execution is used.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS unwraps page_map members inside explicit dictionary_output envelopes before stale adapter pages
PASS unwraps camelCase pageMap members inside raw pdftext JSON cache envelopes
PASS unwraps one-page page_map envelopes inside selected page-list entries
1 test files, 42 assertions, 0 failures
```

Adjacent core/dictionary check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonListEntryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedJsonPagesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedExplicitEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCorePageMapEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
7 test files, 929 assertions, 0 failures
```

Broad dictionary family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfTextDictionary*Test.php' | sort)
30 test files, 2181 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-envelope-currentbase.php
page_map_cache_selected=true
safe_span_link_promoted=true
reference_anchor_synthesized=true
stale_wrapper_excluded=true
cover_appendix_excluded=true
raw_payload_excluded=true
executes_python_pdftext=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCorePageMapEnvelopeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-page-map-envelope-currentbase.php
```

## Dependency Closure

No new native support component is needed. This reuses the existing `pdf-text-dictionary-core` boundary and extends its explicit envelope selection aliases to `page_map` and `pageMap`.

## Non-Overlap

This slice does not touch Type3 CharProc font widths, OCR/model workers, xref repair, stream filters, layout-order geometry, or page-map duplicate-key rejection. It is limited to explicit pdftext dictionary page-list envelope selection.

## Next Task

Continue no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations/forms/security, page geometry, image/filter review metadata, or supplied table/equation handoffs.
