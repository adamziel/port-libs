# markerPDF pdftext dictionary direct page boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T124921Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260606T124921Z`
Accepted base: `b35137156237456f8b66e635831adbb18f2efbfa`

## Source Truth

Upstream markerPDF `marker/pdf/extract_text.py` calls `pdftext.extraction.dictionary_output(...)` and then iterates page dictionaries through `get_text_blocks()`. The relevant supplied-boundary shape is a pdftext page dictionary with top-level `blocks`; this slice keeps that shape as one page instead of flattening it into scalar values.

Pinned upstream source used for this boundary: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

## Change

`PdfTextDocumentExtractor::normalizeSuppliedDictionaryPageList()` now wraps a direct supplied pdftext page dictionary with top-level `blocks` as a single page list before selected-page slicing. This preserves `source_pages=1`, `page_range=[0]`, source page metadata, refs, safe span link promotion, sanitized `char_blocks`, and raw payload exclusion at the native no-GPU boundary.

`examples/wordpress-pdftext-dictionary-direct-page-currentbase.php` maps the same direct-page dictionary path into WordPress paragraph output and records that no Python pdftext, models, OCR, pypdfium/PDFium, or external PDF tools execute.

## Evidence

Baseline focused run before this slice:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files, 255 assertions, 0 failures`

After source/test edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `1 test files, 269 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdftext-dictionary-direct-page-currentbase.php`

Result: passed, with `direct_page_wrapped=true`, `source_pages=1`, `selected_pdftext_page=92`, `safe_span_link_promoted=true`, `raw_chars_excluded=true`, `direct_page_payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat pdftext page/object envelope unwrapping, named `dictionary_output` unwrapping, selected blank pages, sort/order alignment, `keep_chars` sanitation, link/ref preservation, disabled-link behavior, empty-span handling, source dimension/bbox normalization, Unicode repair, quote-loosebox behavior, parser/xref/font/CMap/image/annotation/form/security/table/equation extraction, or any GPU/model/OCR execution.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP supplied pdftext dictionary boundary, block converter, sanitizer, Markdown postprocessor, and WordPress smoke path. Live pdftext/PDFium/Surya/Torch/Texify/tabled/Streamlit/FastAPI execution remains intentionally out of scope for this no-GPU markerPDF lane.
