# Table OCR Layout Structure Boundary

Slice: `table-ocr-layout-structure-boundary-currentbase-20260602T121925Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/tables/table.py::get_table_boxes` collects `Table` layout boxes, rescales them to high-resolution crop coordinates, and passes the high-res table boxes plus pdftext text-line dictionaries into table recognition.
- Locked `tabled-pdf` 0.1.4 `tabled.inference.recognition::get_cells` calls `surya.input.pdflines::get_table_blocks([highres_bbox], text_line, image_size)` when a page has usable pdftext lines.
- Locked `surya-ocr` `surya.input.pdflines::get_table_blocks` requires strong table overlap, splits line character streams into table cells using gap rules, sorts them, and rewrites cell bboxes relative to the input table crop before tabled recognition.

Native PHP behavior added:

- `TableRecognizer` now accepts upstream-shaped pdftext dictionaries with nested `blocks -> lines -> spans -> chars`.
- It filters candidate table lines with the upstream 0.8 table-overlap boundary, splits character streams into cells with the upstream gap heuristic, sorts the result, and converts bboxes from high-res page space to table-local crop space.
- `SuppliedDocumentConverter` now routes recognition outputs that have rows/columns but no cells through `getCells()`, so non-OCR table text-line structures can populate Markdown tables without falling back to empty recognized cells or stale pdftext table text.

Focused evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 2 files, 204 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-layout-structure-boundary.php` emitted a Gutenberg table for `Feature/Status` and `Imported/Ready`, `table_needs_ocr=[false]`, `table_cell_counts=[4]`, first table-cell bbox `[12,10,74,24]`, `excluded_stale_pdftext_table_line=true`, `excluded_outside_layout_textline=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 65 files, 3869 assertions, and 0 failures.
- Changed PHP lint passed for `TableRecognizer.php`, `SuppliedDocumentConverter.php`, `TableRecognizerTest.php`, `SuppliedDocumentConverterTest.php`, and `wordpress-table-ocr-layout-structure-boundary.php`.

Counters:

- `phpPass`: `496 -> 498`.
- Mapped focused semantics: `344 -> 345 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native supplied-document converter, table formatter, table recognizer, and layout/table bbox rescaling paths.
- Full live parity remains dependency-gated on upstream Python/model execution: pdftext dictionary extraction, pypdfium/PIL rendering, Surya OCR/layout, tabled detection/recognition, Torch/model downloads, and benchmark/runtime launch tooling.

Non-overlap:

- This does not repeat the accepted forced-OCR table prediction object, merged table/equation/image arbitration, table formatter crop planning, tabled assignment/markdown formatting, PDF parser/xref, annotation, AcroForm, image/color, font, metadata, or security slices.
- The new behavior is the non-OCR table text-line structure boundary where upstream-shaped high-res pdftext dictionaries populate table cells only after strong table overlap and table-local bbox translation.
