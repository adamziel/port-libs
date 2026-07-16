# Pdftext Dictionary Blank Page Boundary

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260604T133824Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(...)` for a selected `page_range`, then enumerates returned page dictionaries through `pdftext_format_to_blocks()`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked pdftext `0.3.18` returns one dictionary per requested page. Its inference path can return a page dictionary with `blocks: []` when PDFium reports no text characters, and `dictionary_output()` still returns that page after block cleanup: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/inference.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

Added focused current-base coverage for selected blank pdftext dictionary pages:

- selected blank pages with `blocks: []` remain present in the native Marker page list;
- original PDF page number, page range, bbox, and empty `char_blocks` are preserved;
- skipped cover/appendix dictionary pages outside the selected range do not leak into the result;
- Gutenberg paragraph output remains empty for the blank page while paginated output can still expose a page-start marker for WordPress review/import tooling.

No production parser change was needed because the accepted native `PdfTextBlockConverter` and `MarkdownPostProcessor` already matched this upstream boundary.

## Verification

Before this patch:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `2 test files, 94 assertions, 0 failures`.

After this patch:

```sh
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-blank-page-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-blank-page-currentbase.php
git diff --check -- lanes/markerpdf
```

The focused test command passed with `2 test files, 106 assertions, 0 failures`. The WordPress smoke emitted `visible_wordpress_blocks:0`, `selected_blocks:0`, `selected_char_blocks:0`, `paginated_page_start:true`, and excluded skipped cover/appendix text.

## Non-overlap

This does not repeat accepted pdftext dictionary `keep_chars=false` sanitation, span postprocessing, selected page range/options, supplied layout-order trimming, keyed layout/order artifacts, optional `sort=true`, native parser/font/filter/xref extraction, table/equation supplied-boundary work, runtime preflight, or OCR/model paths. The bounded behavior is specifically selected blank searchable-PDF dictionary pages at the pdftext core boundary.

## Dependency Closure

No new support component is needed. This reuses `PdfTextDocumentExtractor`, `PdfTextBlockConverter`, and `MarkdownPostProcessor`. Full upstream runner parity remains gated on live `pdftext`, pypdfium2/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI workers, benchmark tooling, and external OCR/rendering helpers, which remain intentionally out of scope under the no-GPU markerPDF directive.
