# markerPDF pdftext dictionary sort boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260603T222156Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable text pages to `pdftext.extraction.dictionary_output(...)` before `pdftext_format_to_blocks()` converts dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The locked `pdftext` dependency boundary is `0.3.18`; `dictionary_output(sort=True)` calls `sort_blocks()` after dictionary cleanup, grouping blocks by y with a 1.25 tolerance and ordering each row by x before returning pages to markerPDF: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/postprocessing.py

## Implemented behavior

- `PdfTextDocumentExtractor::getTextBlocks()` now accepts `sort: true` and applies native pdftext-style block sorting after supplied dictionary sanitation and before Marker page conversion.
- The default markerPDF path remains unsorted, matching upstream `get_text_blocks()` because markerPDF does not pass `sort=True` in the current pinned source.
- Sorted `char_blocks`, visible page blocks, metadata options, and Gutenberg paragraph output now share the same row-then-column order.
- `getOrderedTextBlocks()` can forward the same sort option for callers that need sorted dictionary pages before supplied layout-order assignment.
- Added a WordPress smoke proving sorted dictionary output without Python, pdftext, pypdfium, OCR, model execution, or external PDF tools.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-sort-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php` passed: `2 test files, 87 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-sort-currentbase.php` passed and emitted sorted Gutenberg paragraph output plus `sorted_like_pdftext_sort_blocks:true` and `default_unsorted_path_preserved:true`.

## Non-overlap

This does not repeat accepted pdftext keep_chars=false sanitation, pdftext span postprocessing, selected-page range semantics, supplied layout-order trimming, native parser/xref/font/filter extraction, or OCR/model/table/equation paths. The bounded behavior is only the optional `dictionary_output(sort=True)` row/column block sort at the supplied pdftext dictionary boundary.

## Dependency closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, Markdown postprocessor, and WordPress smoke path. Full upstream runner parity remains gated on live `pdftext`, pypdfium2/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI workers, benchmark tooling, and external OCR/rendering helpers, which remain intentionally out of scope under the no-GPU markerPDF directive.
