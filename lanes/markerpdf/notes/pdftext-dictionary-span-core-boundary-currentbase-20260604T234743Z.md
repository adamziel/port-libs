# markerPDF pdftext dictionary span core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260604T234743Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` and then stores the returned `page["blocks"]` as Marker `char_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` `0.3.18` builds spans from inference with `text`, `bbox`, `font`, `rotation`, `char_start_idx`, and `char_end_idx`, then removes `chars` when `keep_chars` is false before returning dictionary pages: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/inference.py and https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/extraction.py

## Change

- `PdfTextDocumentExtractor` now sanitizes document-level pdftext span dictionaries down to the core fields emitted by pdftext inference after `dictionary_output(keep_chars=false)`.
- Arbitrary supplied span payload keys such as raw image bytes or debug/private-stream arrays are removed before `PdfTextBlockConverter` stores `char_blocks`.
- Visible WordPress text, font metadata, rotation, `char_start_idx`, and `char_end_idx` remain preserved.
- The existing WordPress pdftext dictionary smoke now injects a decoy span payload and reports that non-core span payload text is excluded from the document and review metadata.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php` passed: 2 test files / 143 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 1 test files / 560 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-import.php` passed and emitted `char_blocks_non_core_span_payload_excluded=true`, `non_core_span_payload_text_excluded=true`, `raw_chars_present=false`, `char_blocks_raw_chars_present=false`, and no Python/pdftext/model/external PDF tool execution.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP supplied pdftext dictionary converter, `PdfTextBlockConverter`, `MarkdownPostProcessor`, and existing WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/table models, Texify, Streamlit/FastAPI workers, benchmark tooling, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted pdftext page-range slicing, `keep_chars=false` raw character removal, block/line core-key stripping, span text normalization, blank-page handling, sorting, layout/order artifact alignment, table OCR dictionary conversion, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is specifically document-level pdftext span core-key allowlisting before WordPress `char_blocks` review metadata.
