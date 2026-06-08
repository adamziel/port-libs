# markerpdf pdftext dictionary BOM JSON envelope current-base slice

- Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T143709Z`
- Accepted base: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`
- Scope: native no-GPU pdftext dictionary core boundary only.

## Source truth

Pinned upstream markerPDF consumes ordered page dictionaries from `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks` before Marker page/block/span conversion. Existing lane coverage already maps JSON object/list envelopes and per-page JSONL-style entries. This slice keeps that same boundary for native WordPress import caches that preserve the page-list JSON with a leading UTF-8 BOM.

## Implementation

- `PdfTextDocumentExtractor::decodeSuppliedDictionaryJsonEnvelope()` now strips a UTF-8 BOM only at the explicit `dictionary_output` / `pdftext` JSON envelope decode boundary.
- Visible span strings and non-envelope sidecar strings are not decoded through this path and keep the existing pdftext dictionary validation/sanitization behavior.

## Verification Results

- Focused test: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBomJsonEnvelopeBoundaryCurrentBaseTest.php` => `1 test files, 24 assertions, 0 failures`.
- Focused family check: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBomJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonListEntryBoundaryCurrentBaseTest.php` => `4 test files, 525 assertions, 0 failures`.
- PHP lint: `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php`, `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBomJsonEnvelopeBoundaryCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-bom-json-envelope-currentbase.php` => no syntax errors.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-bom-json-envelope-currentbase.php` => exits 0 with `bom_json_unwrapped=true`, `safe_span_link_promoted=true`, `reference_anchor_synthesized=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Diff whitespace: `git diff --check -- lanes/markerpdf` => exits 0.
- Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native PHP pdf-text-dictionary-core boundary and does not run Python pdftext, PDFium, OCR, models, raster rendering, or external PDF tools.
