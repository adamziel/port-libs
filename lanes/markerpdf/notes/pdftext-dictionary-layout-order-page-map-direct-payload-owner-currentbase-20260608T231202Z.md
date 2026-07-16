# markerPDF pdftext dictionary layout/order page-map direct payload owner current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T231202Z`
Session: `port-dev-markerpdf-pdf-dictionary-layout-20260608T231202Z`
Base accepted HEAD: `e4c5b8530d7050cd247624ff66dfa0499e76de2a`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` obtains selected searchable PDF pages from `pdftext.extraction.dictionary_output(..., page_range=...)`.
- Upstream layout/order handoff runs after selected page trimming and assigns one layout/order result to each selected Marker page. Native PHP direct payload artifacts that carry nested `page_map` or `pageMap` owner maps must therefore keep those owner keys as page identity before layout annotation or reading-order sorting.

## Implemented Behavior

- `PdfPageArtifactSelector::directPayloadEnvelopePageKeys()` now scans the same envelope aliases used elsewhere by supplied artifact normalization: `pages`, `dictionary_output`, `pdftext`, `page_map`, and `pageMap`.
- A direct layout/order/image artifact with a stale `page_map` or `pageMap` owner key is rejected before selected-page assignment, even if the artifact also has top-level layout/order geometry.
- Stale direct payload owner rows, raw payload strings, and internal selector markers remain excluded from WordPress text and metadata.

## Verification

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php`
- `php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapDirectPayloadOwnerBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapDirectPayloadOwnerBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-direct-payload-owner-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-direct-payload-owner-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapDirectPayloadOwnerBoundaryCurrentBaseTest.php`
  - `1 test files, 26 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php`
  - `30 test files, 1788 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapDirectPayloadOwnerBoundaryCurrentBaseTest.php`
  - `5 test files, 1197 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-direct-payload-owner-currentbase.php`
  - exits `0` and emits `supplied_artifacts_excluded=true`, `source_order_preserved_without_matching_order=true`, `stale_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf`
  - exits `0`.

Focused delta: +2 focused PASS cases, +26 focused assertions, and +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, supplied page artifact selector, layout annotator, layout orderer, supplied document converter, Markdown/WordPress finalizer, and focused PHP TestRunner. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat accepted selected page-range slicing, direct source-key maps, `page_map`/`pageMap` envelope unwrapping, pageMap page-number precedence, raw JSON artifact envelopes, raw JSON keyed values, direct option envelopes, duplicate key rejection, one-above envelope-key rejection, singleton key mismatch guards, direct-key marker conflict handling, typed JSON payload envelopes, current keyed nested payload selection, row-level marker filtering, normalized/named/polygon/coordinate-order geometry, pdftext half-even sorting, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table/equation supplied-boundary handoffs, OCR, or model parity. The bounded behavior is only stale `page_map` and `pageMap` direct-payload owner keys inside otherwise direct layout/order artifacts.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
