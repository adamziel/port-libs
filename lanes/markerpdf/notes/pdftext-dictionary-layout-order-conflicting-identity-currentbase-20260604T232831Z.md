# markerPDF pdftext dictionary layout order conflicting identity current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260604T232831Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` over the selected `page_range` and enumerates only that selected dictionary result into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` removes pages before `start_page` before rendering layout/order images, so supplied native artifacts that carry page identity must agree with the selected pdftext page before assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` zips ordering predictions with selected Marker pages, so contradictory native identity markers should fail closed instead of falling back to positional trust: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented Behavior

- `PdfPageArtifactSelector` now matches marker-bearing supplied artifacts by checking every present page marker category:
  `page_index`/`doc_page_index`/`document_page_index`/`source_page_index`,
  `pnum`/`page`/`pdftext_page`, and one-based `page_number`.
- An artifact is selected only when all present markers agree with the selected source page index and selected pdftext page number.
- Markerless selected-only lists and full-document lists still preserve the accepted positional and range-slicing behavior.
- Added focused extractor and supplied-converter regressions for a conflicting artifact with `source_page_index=1` but `page=90` while the selected pdftext page is `91`.
- Added a WordPress smoke proving the contradictory layout/order artifacts stay excluded, source order is preserved, and skipped cover-page text is not imported.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- Result: `2 test files, 631 assertions, 2 failures`.
- Failures: the selected extractor page was reordered by the conflicting artifact, and the supplied converter emitted `layout`/`order` boundaries for the conflicted cover-page artifact.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php`
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-conflicting-identity-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 641 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - `4 test files, 691 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-conflicting-identity-currentbase.php`
  - emitted `layout_artifacts_excluded=true`, `order_artifacts_excluded=true`, `source_order_preserved_without_trusted_order=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - both JSON files decoded.
- `git diff --check -- lanes/markerpdf`
  - passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP pdftext dictionary conversion, selected page-range handling, supplied artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse keyed artifact matching, selected-count keyed mismatch exclusion, page-identity/source-index collision exclusion, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically rejecting internally contradictory page markers on supplied pdftext layout/order artifacts before selected-page zip-style assignment.
