# Supplied Merged Table Equation Image Boundaries

Slice: `supplied-dictionary-table-equation-image-boundaries-20260602T075730Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/convert.py::convert_single_pdf` runs table formatting before span filtering, equation replacement, and image extraction.
- Upstream `marker/tables/table.py::get_table_boxes` collects `Table` layout boxes, calls `tabled.inference.detection::merge_tables`, filters tiny merged boxes, rescales merged boxes to high-resolution crop coordinates, and later uses those table boxes for Markdown replacement.
- Upstream `marker/equations/equations.py::find_equation_blocks` and `marker/images/extract.py::find_image_blocks` use layout `Formula`, `Figure`, and `Picture` boxes as insertion boundaries. The PHP lane already had WordPress-safe arbitration so table-contained formula/image regions do not create duplicate math or image placeholders after table Markdown has replaced the raw table text.

Native PHP behavior added:

- `DocumentStructureBoundary::layoutRegions($page, ['Table'])` now mirrors the same adjacent-table merge and tiny-box filtering used by `TableFormatter` before returning protected table regions.
- `EquationReplacer` and `ImageExtractor` automatically reuse those merged protected table regions, so a supplied `Formula` or `Picture` box in the seam between two adjacent table detections no longer falls through as a duplicate equation/image block.
- The WordPress output path keeps the merged table Markdown and following paragraph while excluding raw seam text, fallback Texify output, and image placeholders.

Focused evidence:

- Worker focused gate: `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/EquationReplacerTest.php lanes/markerpdf/tests/ImageExtractorTest.php lanes/markerpdf/tests/TableFormatterTest.php` passed with 4 test files, 206 assertions, and 0 failures.
- Supervisor full-lane gate after applying on current integration base `60eb156a5`: `php tools/run-tests.php lanes/markerpdf/tests` passed with 59 test files, 2610 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-supplied-merged-table-boundaries.php` emitted table bbox `[192,400,1120,560]`, `excluded_duplicate_equation=true`, `excluded_duplicate_image=true`, `excluded_raw_table_text=true`, `image_count=0`, and `executes_python_or_models=false`.
- Syntax checks passed for `DocumentStructureBoundary.php`, `SuppliedDocumentConverterTest.php`, and `wordpress-supplied-merged-table-boundaries.php`.

Counters:

- `phpPass`: `434 -> 435`
- mapped focused semantics: `287 -> 288 / 78`

Dependency closure:

- No new support component is needed. The slice reuses the existing native supplied-document converter, table formatter/recognizer, equation replacer, image extractor, and layout rescaling helpers.
- Full live parity remains gated on the upstream Python/model stack: pypdfium2/PIL rendering, Surya layout/order/OCR, tabled-pdf detection/recognition, Texify, Torch, and benchmark runner dependencies.

Non-overlap:

- This does not repeat the accepted page-box/rotation/UserUnit, PDF parser/xref/object-stream, font/CMap, annotation, encryption, metadata, inline-image, image-filter, or direct single-table structure-boundary slices.
- The new behavior is specifically the supplied-dictionary seam case where upstream table detection merges adjacent `Table` boxes and that merged table boundary must protect nested `Formula` and `Picture` layout regions before WordPress rendering.
