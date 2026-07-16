# markerPDF pdftext dictionary layout/order object-point polygon boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T051058Z`

Base accepted HEAD: `27a0520cbee0c34db64918d6587918843c9b97db`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then converts each selected dictionary page before layout/order sorting: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are applied: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py>
- `marker/layout/order.py::surya_order()` zips ordering predictions to selected pages, and `sort_blocks_in_reading_order()` applies each order row's geometry after rescaling from `order.image_bbox` into page space: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py>

## Implemented Behavior

- `LayoutOrderer` now accepts polygon points serialized as object dictionaries with `x`/`y`, `x0`/`y0`, or `left`/`top` coordinates while ignoring harmless adapter metadata fields such as confidence or score.
- `LayoutAnnotator` uses the same point parsing for supplied layout polygons, including title/text block promotion before WordPress Markdown finalization.
- Existing two-value list polygon points remain supported, and malformed or non-finite point coordinates still fail closed by dropping the supplied row.
- Raw adapter payload strings stay out of returned metadata and visible WordPress paragraphs.
- Added a WordPress smoke for selected pdftext dictionary pages whose supplied layout/order polygons use object-shaped points.

## Red-First Evidence

Before the source fix, after adding the focused assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL uses object-point polygon order rows before pdftext layout assignment
FAIL uses object-point polygon layout and order rows for WordPress supplied imports
1 test files, 373 assertions, 2 failures
```

The order case stayed in source order because object-point polygons were ignored. The WordPress case did not promote the title because supplied layout polygons with metadata-bearing point objects were ignored.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 387 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 854 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-object-polygon-currentbase.php
```

The smoke emits `object_polygon_layout_assigned=true`, `object_polygon_order_assigned=true`, `title_before_body=true`, `cover_excluded=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +26 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, plus one WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected-page artifact selection, supplied layout annotation, supplied ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR/table-cell models, Texify, tabled-pdf model execution, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, sparse keyed matching, duplicate keyed artifact rejection, wrapper-list marker extraction, typed result wrapper sanitation, normalized/named/list bbox rows, zero-area/non-finite geometry guards, polygon list-point rows, table/OCR polygon geometry, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically object-shaped polygon points with extra adapter metadata in supplied layout/order geometry before selected pdftext dictionary pages are converted to WordPress paragraphs.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
