# markerPDF pdftext dictionary rotated normalized bbox current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T142220Z`

Base accepted HEAD: `a067b67e65d976924ff847772b4bfe12fe0932ce`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and then converts each returned page with `pdftext_format_to_blocks()`.
- `pdftext.extraction.dictionary_output(...)` processes block, line, and span bboxes before it swaps page `width`/`height` for 90/270 degree pages and reverses the page bbox.

## Implemented Behavior

- `PdfTextDocumentExtractor` now scales normalized child bboxes on rotated supplied pdftext pages from the source page bbox extent when the page dictionary already has swapped `width`/`height`.
- Non-rotated normalized dictionary pages keep using the existing width/height scaling path.
- Rotated page source metadata still preserves the pdftext page bbox, swapped width/height, and rotation, while the rendered Marker page bbox remains rotation-adjusted.
- Arbitrary page/block/line/span payloads remain excluded before WordPress paragraph rendering.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextDocumentExtractor.php

php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-rotated-normalized-bbox-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-rotated-normalized-bbox-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 159 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 488 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-rotated-normalized-bbox-currentbase.php
exit 0; emits rotated_bbox_scaled_from_source_page=true, payload_excluded=true, visible_wordpress_text="Rotated normalized bbox", executes_python_pdftext=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

Focused delta: +1 focused PASS case in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`; +1 WordPress smoke example.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary conversion, supplied dictionary sanitizer, bbox normalization path, Markdown post-processing, and WordPress smoke renderer. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/order/table-cell models, Texify, Streamlit/FastAPI workers, page-pixel visual recognition, and external PDF tools remain intentionally outside the no-GPU markerPDF scope.

## Non-Overlap

This does not repeat accepted pdftext page-source metadata preservation, page payload stripping, normalized non-rotated bbox scaling, off-page bbox scaling, finite-number validation, page rotation validation, span/character rotation metadata, keep-chars sanitation, link/ref behavior, disable-links behavior, dictionary sorting, blank-page handling, layout/order artifact alignment, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security preflight, table recognition, OCR, equation handoff, or stream-filter work. The bounded behavior is only rotated supplied pdftext dictionary pages whose normalized child bboxes must scale from source page bbox dimensions after dictionary_output has swapped page width/height.
