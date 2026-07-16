# markerPDF pdftext dictionary layout order page_idx current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T221051Z`

Source truth:
- Upstream `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF extraction to `pdftext.extraction.dictionary_output(..., page_range=...)`.
- Upstream Marker trims the selected PDF page range before layout/order handoff; native PHP supplied-boundary adapters therefore must match layout/order artifacts to the selected pdftext pages before falling back to positional assignment.
- Native adapters commonly expose zero-based page identity as `page_idx`; selected-page aliases such as `selected_page_idx`, `trimmed_page_idx`, and `relative_page_idx` must behave like the accepted `*_page_index` keys without running OCR, Surya, Texify, Torch, or external PDF tools.

Implemented behavior:
- `PdfPageArtifactSelector` now recognizes zero-based `page_idx`, `doc_page_idx`, `document_page_idx`, and `source_page_idx` as source-document page markers, plus `selected_page_idx`, `trimmed_page_idx`, and `relative_page_idx` as selected-page markers.
- `LayoutAnnotator` and `LayoutOrderer` accept and preserve the same scalar page-marker aliases when sanitizing supplied layout/order results.
- Added focused coverage proving stale cover-page `page_idx: 0` artifacts are not assigned positionally after `start_page: 1`, while selected `page_idx: 1` artifacts still drive layout/order assignment.
- Added a WordPress supplied-boundary smoke where selected pdftext dictionary text is ordered from `page_idx` layout/order artifacts, cover/appendix pages remain excluded, and raw adapter payload strings do not leak.

Red-first evidence:
- Before the source change, `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` failed the two new cases: `1 test files, 257 assertions, 2 failures`.
- The extractor case assigned the stale cover `page_idx: 0` order artifact positionally, producing `First page idx alias column` before `Second page idx alias column`.
- The WordPress converter case did not preserve the selected `page_idx` metadata, so the selected layout/order assignment count was `NULL` instead of `1`.

Focused verification:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` -> `1 test files, 282 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` -> `5 test files, 1423 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-idx-currentbase.php` -> emitted selected WordPress paragraphs plus a metadata comment with `page_idx_layout_artifacts_trimmed`, `page_idx_order_artifacts_trimmed`, `page_idx_metadata_matched`, `first_before_second`, `cover_excluded`, `appendix_excluded`, `stale_payload_excluded`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:
- No new support component is needed. This reuses the existing native PHP pdftext dictionary converter, supplied page-artifact selector, layout annotator, orderer, supplied document converter, and Markdown finalizer.
- Live pdftext/PDFium execution, OCR, Surya, Texify, Torch, GPU/model workers, and exact upstream model parity remain intentionally out of scope for this no-GPU markerPDF lane.

Non-overlap:
- This does not repeat accepted full-list trimming, keyed/wrapped/nested sparse matching, selected-index aliases, `page_num`/`page_number`, `page_range`, `pdftext_source`, typed result wrappers, mixed wrapper-list rejection, numeric/string/signed/non-finite marker handling, bbox/polygon/zero-area geometry, payload sanitation, CMap/font/xref repair, stream filters, annotations/forms/security preflight, table/equation handoffs, or OCR/model parity slices.
- The bounded behavior is only zero-based `*_page_idx` marker aliases at the native supplied layout/order boundary.

Next task:
- Continue native searchable-PDF import fidelity with a non-overlapping parser/converter edge such as font/CMap text extraction, stream filters, xref repair, image/filter metadata, annotations/forms/security preflight, or another supplied-boundary handoff not covered by page marker aliases.
