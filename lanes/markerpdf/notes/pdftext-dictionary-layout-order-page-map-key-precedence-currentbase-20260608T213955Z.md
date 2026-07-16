# markerPDF pdftext dictionary layout/order pageMap key precedence current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T213955Z`
Session: `port-dev-markerpdf-pdf-dictionary-layout-20260608T213955Z`
Base accepted HEAD: `ba1acddf7dda63f41a17e1f25945a52ff91962c3`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` consumes `pdftext.extraction.dictionary_output(...)` over the selected page range, and the resulting dictionaries retain explicit page metadata.
- Upstream layout/order handoff runs after selected page trimming and pairs one supplied image/result with each selected Marker page. Native PHP pageMap sidecars therefore need deterministic selected-page identity before layout annotation or reading-order sorting.

## Implemented Behavior

- `PdfPageArtifactSelector` now gives a pageMap key that equals the selected pdftext page number a slightly higher match score than a key that only equals the zero-based selected source index.
- This resolves the small-document collision where selected source index `1` and pdftext page number `2` both appear in a direct `pageMap` JSON object.
- The selected page-number payload is used for layout/order assignment, while stale source-index payloads and internal selector markers remain excluded from WordPress text and metadata.

## Verification

Red-first probe before implementation:

- `PdfPageArtifactSelector::select(["pageMap" => "{\"1\":{\"image\":\"cover\"},\"2\":{\"image\":\"selected\"}}"], 2, [1], 1, [2])` returned `[]`.
- A matching `SuppliedDocumentConverter` probe produced no `layout_plan`, no `order_plan`, and source-ordered text for selected page index `1` with pdftext page number `2`.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapKeyPrecedenceBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-key-precedence-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapKeyPrecedenceBoundaryCurrentBaseTest.php` => 1 test file / 35 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php` => 28 test files / 1724 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-key-precedence-currentbase.php` => exits 0 with `page_map_page_number_preferred=true`, `heading_before_body=true`, `source_index_payload_excluded=true`, `page_number_payload_excluded=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +35 focused assertions, and +1 WordPress smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown/WordPress finalizer, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat accepted selected page-range slicing, direct source-key maps, page_map envelope unwrapping, raw JSON artifact envelopes, raw JSON keyed values, direct option envelopes, duplicate key rejection, one-above envelope-key rejection, singleton key mismatch guards, direct-key marker conflict handling, typed JSON payload envelopes, row-level marker filtering, geometry/bbox/polygon sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table/equation supplied-boundary handoffs, OCR, or model parity. The bounded behavior is only pageMap key precedence when an explicit pdftext page-number key and a zero-based source-index key both match the selected page.
