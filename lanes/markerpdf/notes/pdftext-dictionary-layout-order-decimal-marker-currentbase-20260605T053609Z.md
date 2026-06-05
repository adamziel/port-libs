# markerPDF pdftext dictionary layout order decimal marker current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T053609Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` over the selected `page_range`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are applied.
- `marker/layout/order.py::surya_order()` zips supplied order predictions with selected Marker pages, so native supplied artifacts must normalize page identity before zip-style assignment.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats integer-valued decimal strings such as `231.0` and `231.000` as page markers.
- `LayoutAnnotator` and `LayoutOrderer` use the same decimal-integer normalization when preserving sanitized layout/order page metadata.
- Sparse supplied layout/order artifacts with decimal string page identity now align to selected pdftext pages before WordPress paragraph ordering.
- Non-integer decimal strings still do not become page markers.
- Added a WordPress smoke for decimal string pdftext dictionary layout/order markers.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` failed at `normalizes decimal string page markers before selected pdftext layout order assignment`; selected-page blocks remained source ordered.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed at `normalizes decimal string page markers before supplied layout and order alignment`; expected selected-page `layout_result_count` `1`, actual `2`.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/src/LayoutAnnotator.php` passed.
- `php -l lanes/markerpdf/src/LayoutOrderer.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-decimal-marker-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed with `4 test files, 985 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-decimal-marker-currentbase.php` passed and emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `decimal_marker_page_preserved=true`, `first_before_second=true`, `cover_excluded=true`, and `appendix_excluded=true`.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " valid\n"; }'` passed.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +19 focused assertions across `PdfTextDocumentExtractorTest.php` and `SuppliedDocumentConverterTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, page-artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat the accepted selected-page trimming for full-document artifact lists, sparse keyed marker matching, wrapped/nested marker extraction, whitespace integer marker normalization, exact-marker precedence, selected-index matching, duplicate-marker reuse prevention, nested pdftext payload exclusion, pdftext dictionary sorting, keep-chars sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, or equation/image supplied-boundary work. The bounded behavior is integer-valued decimal string page-marker normalization before supplied pdftext dictionary layout/order artifact assignment.
