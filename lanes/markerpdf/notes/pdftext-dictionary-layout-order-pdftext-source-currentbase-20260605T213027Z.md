# pdftext_source layout/order boundary current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T213027Z`

Source truth:
- Upstream `marker/pdf/extract_text.py::get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(..., page_range=...)`, then Marker zips selected PDF page artifacts through layout and order boundaries.
- The native PHP port already preserves the original pdftext page dictionary in `pdftext_source`; supplied layout/order adapters can carry the selected document page there without executing OCR, Surya, Texify, Torch, or model workers.

Implemented behavior:
- `PdfPageArtifactSelector` now treats `pdftext_source` as trusted page-marker metadata before positional selected-page assignment.
- `LayoutAnnotator` and `LayoutOrderer` accept the same wrapper while sanitizing supplied layout/order results, copying scalar page markers only and not copying the `pdftext_source` payload wrapper.
- Added focused coverage for sparse cover/selected/appendix artifacts where only `pdftext_source.page` identifies the selected pdftext page.
- Added a WordPress smoke for supplied layout/order import where selected text is ordered from the `pdftext_source.page` artifacts and cover/appendix/payload text remains excluded.

Red-first evidence:
- Before the source change, `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` failed the new cases with positional fallback:
  - extractor expected `First pdftext source column`, `Second pdftext source column`; actual order was reversed.
  - WordPress converter expected one selected layout artifact; actual selected count was `2`.

Focused verification:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` -> `1 test files, 251 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` -> `5 test files, 1392 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-pdftext-source-currentbase.php` -> emitted selected WordPress paragraphs plus metadata comment with `layout_artifacts_trimmed`, `order_artifacts_trimmed`, `first_before_second`, `cover_excluded`, `appendix_excluded`, and `payload_excluded` all true.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP pdftext dictionary converter, supplied-artifact selector, layout annotator, and orderer.
- No external PDF tools, OCR, Python, GPU, Surya, Texify, Torch, or model execution was added.

Non-overlap:
- This does not revisit normalized bbox scaling, typed wrapper unwrapping, stale nested `pdftext` fallback handling, `page_range`, `page_num`, mixed wrapper-list rejection, CMap/font/xref repair, or OCR/model parity slices.

Next task:
- Continue native searchable-PDF import fidelity with another bounded parser/converter edge, preferably font/CMap text extraction, stream filters, xref repair, image metadata, or another supplied-boundary handoff not already covered by this layout/order metadata path.
