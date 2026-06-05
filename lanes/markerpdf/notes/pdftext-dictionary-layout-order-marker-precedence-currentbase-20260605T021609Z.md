# markerpdf pdftext dictionary layout/order marker precedence current-base

## Source truth

- Upstream `sddai/markerPDF` pinned in `UPSTREAM_TEST_MANIFEST.json` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()`, receiving page dictionaries from `pdftext.extraction.dictionary_output`.
- Upstream trims the PDFium/pdftext page range before rendering layout/order pages and then applies ordering predictions to the selected relative pages. The native PHP supplied-boundary path therefore must not let stale whole-document artifacts override the exact selected pdftext page when both are present.

## Implemented behavior

- `PdfPageArtifactSelector` now scores matching page markers instead of using the first matching artifact.
- Exact selected-page markers from `page`, `pnum`, and `pdftext_page` outrank weaker one-based `page_number` markers when both match the selected pdftext dictionary page.
- Conflicting marker categories still fail closed, and sparse/missing selected pages still use the existing missing-artifact sentinel.
- This prevents stale one-based collision artifacts from reversing selected-page layout/order columns before WordPress paragraph rendering.

## Evidence

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-marker-precedence-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` => 1 test files / 152 assertions / 0 failures, focused delta 143 -> 152.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => 1 test files / 649 assertions / 0 failures, focused delta 637 -> 649.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-marker-precedence-currentbase.php` => emits `exact_page_marker_wins=true`, `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted sparse keyed, wrapped keyed, nested adapter, whitespace string marker, selected-count mismatch, source-index collision, conflicting identity, or partial keyed supplied-artifact behavior. The new boundary is marker precedence among multiple otherwise matching artifacts for the same selected page: exact page identity now wins over a weaker one-based `page_number` collision.

## Dependency closure

No new support component is needed. This reuses the native pdftext dictionary converter, supplied page artifact selector, layout annotation/order handoff, and WordPress smoke path. Full upstream pdftext/PDFium execution, Surya layout/order models, OCR, Texify, Torch/model batching, Streamlit/FastAPI workers, and exact upstream benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
