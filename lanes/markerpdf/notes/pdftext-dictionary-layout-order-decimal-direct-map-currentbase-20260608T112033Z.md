# markerPDF pdftext dictionary layout/order decimal direct-map boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T112033Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` over the selected `page_range`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are applied.
- `marker/layout/order.py::surya_order()` zips supplied order predictions with selected Marker pages, so native supplied layout/order maps must pass the same source-page key grammar before selected-page assignment.

## Implemented Behavior

- `SuppliedDocumentConverter` now accepts top-level source-page keyed `lowres_images`, `layout_results`, `order_images`, and `order_results` maps whose keys use signed integer `.0` spelling, for example `+9811.0`.
- This aligns converter option validation with the existing `PdfPageArtifactSelector` and pdftext dictionary page-map normalization.
- The selected decimal-keyed current page is assigned to layout/order, stale cover maps are excluded, and selector-only key markers plus raw adapter payloads stay out of WordPress review metadata.
- Added a WordPress smoke for decimal direct-map pdftext dictionary layout/order imports.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalDirectMapBoundaryCurrentBaseTest.php`
- Failed with: `markerPDF supplied document option lowres_images must be a list or source-page keyed map.`

Green after implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalDirectMapBoundaryCurrentBaseTest.php`
- Passed: `1 test files, 19 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-decimal-direct-map-currentbase.php`
- Exits 0 and emits `layout_direct_map_selected=true`, `order_direct_map_selected=true`, `first_before_second=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case and +19 focused assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, source-page artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected-page trimming for full-document artifact lists, sparse keyed `page`/`page_number` marker matching, nested dictionary-output map unwrapping, JSON artifact envelopes, decimal nested map keys, singleton key mismatch rejection, duplicate normalized page-key rejection, page-range metadata trust, geometry normalization, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, or equation/image supplied-boundary work. The bounded behavior is only top-level direct source-page keyed supplied layout/order maps whose numeric keys use signed `.0` spelling.
