# markerPDF pdftext dictionary quote_loosebox current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T131139Z`

Base accepted HEAD: `6a7cca96e3041d70a102c1990a9f40af70809228`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks()` before converting returned page dictionaries with `pdftext_format_to_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked pdftext behavior exposes `dictionary_output(..., quote_loosebox=True, disable_links=False, workers=None)` and forwards `quote_loosebox` into `_get_pages()`/`get_pages()` before block/line/span dictionary post-processing: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

`PdfTextDocumentExtractor` now carries the pdftext `quote_loosebox` option through the native supplied dictionary boundary:

- `getTextBlocks()` accepts `quoteLoosebox`, defaulting to upstream `true`;
- explicit `quoteLoosebox: false` is preserved in `metadata["pdftext_options"]["quote_loosebox"]`;
- `getOrderedTextBlocks()` forwards the same option before supplied layout/order handoff;
- visible WordPress paragraph text, page range, links, refs, char-block sanitation, and no-GPU boundaries are unchanged.

Added `examples/wordpress-pdftext-dictionary-quote-loosebox-currentbase.php` to model a WordPress import that records default and strict quote-loosebox extraction options as review metadata without executing Python pdftext, PDFium, OCR, models, or external PDF tools.

## Red/Green Evidence

Red-first focused check before implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files / 427 assertions / 2 failures`; failures were `Unknown named parameter $quoteLoosebox` and missing `quote_loosebox` in `pdftext_options`.

Focused check after implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `2 test files / 432 assertions / 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-quote-loosebox-currentbase.php
```

Result: emitted `default_quote_loosebox_recorded=true`, `strict_quote_loosebox_recorded=true`, visible text `Quote loosebox option remains review metadata.`, and all Python/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted pdftext keep-chars sanitation, span core-key filtering, text post-processing, normalized/off-page bbox handling, page-source geometry, disable_links, link/ref metadata, sorting, blank-page handling, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter metadata, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior. The bounded behavior is only `quote_loosebox` option propagation and review metadata at the pdftext dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, ordered-page handoff, Markdown/WordPress smoke path, and focused PHP tests. Live `pdftext`, pypdfium/PDFium, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
