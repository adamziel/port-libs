# markerpdf pdftext dictionary nested JSON pages current-base slice

- Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T150911Z`
- Accepted base: `4c862b32f8029fb79956472ec44c66aa3f81547c`
- Scope: native no-GPU pdftext dictionary core boundary only.

## Source truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks`, then enumerates the returned page dictionaries before Marker page/block/span conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` `0.3.18` `dictionary_output()` returns ordered page dictionaries and strips block/line/span data to the core dictionary shape before returning: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Red proof

Before the source edit, this current-base reproduction failed with `InvalidArgumentException: Supplied pdftext dictionary_output envelope must contain a page dictionary or page list.`:

```text
php -r 'require "tools/bootstrap.php"; $page=["page"=>1,"bbox"=>[0.0,0.0,100.0,100.0],"width"=>100.0,"height"=>100.0,"rotation"=>0,"blocks"=>[["bbox"=>[1.0,1.0,20.0,10.0],"lines"=>[["bbox"=>[1.0,1.0,20.0,10.0],"spans"=>[["text"=>"Nested pages json\n","bbox"=>[1.0,1.0,20.0,10.0],"font"=>["name"=>"Helvetica","flags"=>0,"weight"=>400,"size"=>11.0]]]]]]]]; $json=json_encode([1=>$page], JSON_THROW_ON_ERROR); try { $doc=(new PortLibs\MarkerPDF\PdfTextDocumentExtractor())->getTextBlocks(["dictionary_output"=>["pages"=>$json]], maxPages:1); echo $doc["pages"][0]["pnum"]."\n"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage()."\n"; }'
```

## Implementation

- `PdfTextDocumentExtractor` now decodes raw JSON strings only at explicit nested page-list envelope boundaries, including `dictionary_output.pages`, `pdftext.pages`, and `pages.pages`.
- The change reuses the existing BOM-aware explicit-envelope decoder; arbitrary visible span strings and sidecar strings are not decoded.
- Stale top-level adapter pages, envelope metadata, raw payloads, skipped pages, and selected-page private payload keys stay excluded from Marker pages, `char_blocks`, `pdftext_source`, and WordPress paragraphs.

## Verification results

- Focused new test: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedJsonPagesBoundaryCurrentBaseTest.php` => `1 test files, 30 assertions, 0 failures`.
- Focused family check: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedJsonPagesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBomJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonListEntryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` => `5 test files, 555 assertions, 0 failures`.
- PHP lint: `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php`, `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreNestedJsonPagesBoundaryCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-nested-json-pages-currentbase.php` => no syntax errors.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-nested-json-pages-currentbase.php` => exits 0 with `nested_pages_json_unwrapped=true`, `safe_span_link_promoted=true`, `reference_anchor_synthesized=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Root harness: not run - isolated micro-slice.

Focused delta: +2 focused TestRunner PASS cases, +30 focused assertions, and +1 WordPress smoke. `lane-status.json` `phpPass` moves `3204 -> 3206`; `wordpressScenarios` moves `2625 -> 2626`; mapped denominator is unchanged.

## Dependency closure

No new support component is needed. This slice reuses the native PHP pdf-text-dictionary-core boundary, object normalization, dictionary-output sanitation, safe URI/ref handling, and Markdown/WordPress smoke rendering. It does not run Python pdftext, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell models, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, online services, or external PDF tools.

## Non-overlap

This does not repeat accepted top-level raw JSON `dictionary_output`/`pdftext` strings, UTF-8 BOM-prefixed top-level cache strings, raw JSON page-list entries, direct page dictionaries, singleton envelopes, duplicate page-key handling, link/ref preservation, keep-chars validation, char-index validation, Unicode repair, bbox normalization, sorting, blank-page handling, layout/order artifact routing, parser/xref repair, font/CMap/native PDF extraction, image/filter metadata, annotations/forms/security, OCR/model handoffs, or table/equation supplied-boundary work. The bounded behavior is only raw JSON nested `pages` member decoding at the explicit pdftext dictionary page-list envelope boundary.
