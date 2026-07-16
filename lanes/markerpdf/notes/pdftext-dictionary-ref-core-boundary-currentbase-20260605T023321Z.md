# markerPDF pdftext dictionary ref core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T023321Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T023321Z`
Base accepted HEAD: `051bcdbceaab830e525a7a4131c12c75c9e7e604`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` and stores the returned page dictionaries as Marker `char_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.extraction.dictionary_output()` calls `add_links_and_refs()` before reducing block/line/span dictionaries to their core fields: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py
- `pdftext.pdf.links.add_links_and_refs()` builds page `refs` from `PageReference` and uses span `url` metadata for link reconstruction; `pdftext.schema.Page` models refs as link/reference metadata, not arbitrary payload dictionaries: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/links.py and https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py

## Change

`PdfTextBlockConverter` now sanitizes page-level `pdftext_source.refs` before preserving them as review metadata:

- keeps source-shaped keys `url`, `page`, `dest_pos`, `dest_page`, `bbox`, `idx`, `ref`, and `coord`;
- validates numeric page, bbox, and coordinate operands;
- drops payload-only ref rows and arbitrary adapter/private keys such as raw PDF bytes and debug streams;
- keeps unsafe span URLs review-only as before, and safe span URLs still render through WordPress Markdown.

This closes the remaining page-level pdftext dictionary core boundary after the prior character/font sanitation slice.

## Red First

Before the source change, the focused regression failed:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `FAIL sanitizes pdftext page refs at the source metadata boundary`; actual `pdftext_source.refs` still included `raw_private_payload`, `debug_payload`, `raw_pdf_bytes`, and a payload-only row.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` => `1 test files, 43 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php` => `3 test files, 232 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-link-ref-currentbase.php` emitted `safe_pdftext_url_promoted=true`, `unsafe_pdftext_url_review_only=true`, `pdftext_refs_preserved=true`, `pdftext_reference_shape_preserved=true`, `pdftext_ref_payload_excluded=true`, `raw_chars_excluded=true`, and no Python/pdftext/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted pdftext keep-chars character sanitation, span font payload sanitation, selected page-range slicing, sorted/blank dictionary pages, sparse layout/order matching, OCR/table supplied-boundary routing, parser/xref repair, font/CMap/width extraction, image/filter review, annotations/forms/security preflight, or runtime conversion preflights. The bounded behavior is only page-level pdftext `refs` source metadata sanitation before WordPress import review.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, source metadata preservation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
