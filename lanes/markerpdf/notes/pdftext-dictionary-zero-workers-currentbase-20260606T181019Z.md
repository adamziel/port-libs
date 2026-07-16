# pdftext dictionary zero-worker boundary current-base

Date: 2026-06-06 UTC
Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T181019Z`
Base: `e47980bda3ac672a10fc05e8f11f982bb0b3ae43`

## Source truth

- Upstream markerPDF `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF extraction to pdftext `dictionary_output(...)` with a worker count from settings.
- Upstream pdftext `pdftext/extraction.py::_get_pages()` treats `workers is None` or `workers <= 1` as the single-process sequential page loop; only values greater than one use multiprocessing.
- Source links:
  - https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py

## Implementation

`PdfTextDocumentExtractor::getTextBlocks()` now accepts `workers: 0` and records it in `metadata.pdftext_options.workers`, matching upstream's sequential boundary. Negative worker counts remain fail-closed before WordPress import.

The focused dictionary test covers selected page metadata, span id restart, Markdown safe-link promotion, unsafe URL retention only in reviewable `char_blocks`, and negative-worker rejection.

The WordPress smoke emits a Gutenberg paragraph through the native supplied-pdftext dictionary path and confirms no Python, live pdftext, OCR/model, or external PDF tools are invoked.

## Verification

Red-first before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files, 276 assertions, 1 failures` on the stale `workers >= 1` guard.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files, 284 assertions, 0 failures`.

Adjacent pdftext family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php`

Result: `3 test files, 631 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdftext-dictionary-zero-workers-currentbase.php --self-test`

Expected flags: `zero_workers_recorded=true`, `sequential_page_imported=true`, `safe_link_promoted=true`, `unsafe_link_review_only=true`, `char_blocks_keep_source_url=true`, `negative_workers_rejected=true`.

## Non-overlap

This patch does not touch live OCR, Surya, Texify, Torch, model workers, xref repair, XMP metadata parsing, stream filters, annotations, forms, image decoding, or table/equation model handoffs. It is limited to the searchable-PDF supplied pdftext dictionary worker-count boundary.

## Dependency closure

No new support component is needed. The existing native pdftext dictionary converter, Markdown postprocessor, link sanitizer, and WordPress smoke path are reused. Live pdftext/PDFium execution remains outside this no-GPU isolated slice.
