# markerPDF pdftext dictionary singleton envelope core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260607T114205Z`

Session: `port-dev-markerpdf-pdftext-dictionary-20260607T114205Z`

Base accepted HEAD: `1d32bf7438c6252c48b840d4e31b05d4350e0698`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)`, then enumerates the returned page dictionaries before converting each page into Marker `Page` and `Span` structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` `dictionary_output(...)` returns an ordered page list after scaling block/line/span bboxes and stripping block/line payload keys: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor::normalizeSuppliedDictionaryPageList()` now treats a direct page dictionary under `dictionary_output`, `pdftext`, or `pages` as a singleton page list.
- Explicit `dictionary_output` and `pdftext` envelopes still win over stale adapter `pages`, but their direct singleton value is no longer flattened into scalar page fields.
- Singleton pages still pass through the existing object normalization, page sanitizer, safe URI/ref handling, text normalization, bbox scaling, and converter validation.
- Envelope-level adapter payloads, stale pages, and private page/block/line/span/ref payloads stay out of Marker pages, `char_blocks`, `pdftext_source`, and visible WordPress output.
- Added a WordPress smoke for a direct `dictionary_output` singleton envelope over stale adapter pages.

## Verification

Red before implementation:

```text
php -r 'require "tools/bootstrap.php"; ... (new PortLibs\MarkerPDF\PdfTextDocumentExtractor())->getTextBlocks(["dictionary_output"=>$page], maxPages: 1);'
=> InvalidArgumentException: Supplied pdftext page entries must be arrays.
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
=> 1 test files, 348 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-singleton-envelope-currentbase.php
=> emitted singleton_envelope_wrapped=true, safe_span_link_promoted=true, pdftext_ref_anchor_synthesized=true, stale_pages_excluded=true, payload_excluded=true, executes_python_or_models=false, executes_ocr=false, and executes_external_pdf_tools=false.

php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-singleton-envelope-currentbase.php
=> all passed

git diff --check -- lanes/markerpdf
=> passed
```

Focused delta: +1 focused PASS case, +24 assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`, and +1 WordPress smoke. `lane-status.json` `phpPass` moves `2850 -> 2851`; `wordpressScenarios` moves `2390 -> 2391`; mapped manifest denominator is unchanged.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary converter, recursive object normalization, dictionary-output sanitation, safe URI/ref handling, Markdown post-processing, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell model execution, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat top-level direct page dictionaries, nested page-map envelopes, explicit page-list envelopes, `dictionary_output`/`pdftext` precedence, link/ref preservation, disable-links handling, keep-chars validation, character-index validation, Unicode repair, bbox normalization, sort handling, blank pages, layout/order artifact routing, source-key layout/order maps, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary behavior. The bounded new behavior is only singleton direct pdftext page dictionaries stored inside named core envelopes before selected-page conversion.
