# markerPDF pdftext dictionary off-page bbox current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T052000Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T052000Z`
Base accepted HEAD: `689a1d63f07b4ac9ee6dd4da0f28692001c18354`

## Source Truth

- Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` over the selected page range before converting dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext==0.3.18` `dictionary_output()` scales block, line, span, and kept-character bboxes with `unnormalize_bbox(page_width, page_height)`: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/extraction.py
- `pdftext.pdf.utils::unnormalize_bbox()` multiplies coordinates directly and does not clamp negative or greater-than-page normalized values: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/pdf/utils.py

## Change

`PdfTextDocumentExtractor` now treats modestly off-page normalized bbox values as normalized dictionary-output coordinates before scaling:

- block and line bboxes such as `[-0.06, 0.10, 1.32, 0.14]` scale by page width/height instead of staying tiny normalized values;
- rendered spans and stored `char_blocks` receive the same scaled review coordinates;
- `keepChars: true` character bboxes also scale before WordPress review metadata;
- visible Gutenberg paragraph text remains the sanitized pdftext span text, and no live `pdftext`, PDFium, OCR, or model execution is invoked.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-offpage-bbox-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 208 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 307 assertions, 0 failures
```

The adjacent pdftext family was `3 test files, 300 assertions, 0 failures` before this slice; the new off-page bbox case adds 7 focused assertions and 1 PASS case.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-offpage-bbox-currentbase.php
```

The WordPress smoke emitted `offpage_block_bbox_scaled=true`, `offpage_span_bbox_scaled=true`, `offpage_char_bbox_scaled=true`, `visible_wordpress_text=Off-page glyph boxes remain reviewable`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, normalized in-page bbox scaling, `keep_chars=false` payload removal, `keepChars: true` character/font validation, link/ref sanitation, span script flags, pdftext dictionary sorting, blank-page preservation, layout/order artifact trimming, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR/table/equation supplied-boundary work, or inline-image decode boundaries. The bounded behavior is only off-page normalized pdftext dictionary bbox scaling at the supplied dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext supplied-dictionary sanitizer/converter, Markdown merge path, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
