# markerPDF pdftext dictionary layout/order non-finite bbox boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T031954Z`

Accepted base: `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then converts each selected dictionary page before layout/order sorting.
- `marker/layout/order.py::surya_order()` zips ordering predictions to selected pages, and `sort_blocks_in_reading_order()` applies supplied order geometry after rescaling from `order.image_bbox` into page space.
- The native no-GPU boundary accepts supplied layout/order artifacts from PHP adapters instead of running Surya. Adapter geometry must be finite numeric page geometry before it can affect WordPress paragraph order, block-type promotion, or review metadata serialization.

## Implemented Behavior

- `LayoutOrderer` now rejects non-finite numeric bbox coordinates from supplied order geometry.
- `LayoutAnnotator` now sanitizes supplied `image_bbox` and layout `bboxes` into finite positive-area canonical rectangles before storing layout review metadata.
- Non-finite layout rows no longer promote text to headings, non-finite order rows no longer reorder selected pdftext blocks, and finite rows in the same artifact remain usable.
- Raw per-row adapter payloads remain excluded from WordPress text and JSON review metadata.
- Added a WordPress smoke for non-finite supplied layout/order geometry on selected pdftext dictionary pages.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL rejects non-finite supplied order bboxes before selected pdftext layout assignment
FAIL rejects non-finite supplied layout and order geometry before WordPress imports
1 test files, 321 assertions, 2 failures
```

The failing order case let an `INF` bbox row cover both columns and reorder the selected page. The layout case did not provide JSON-safe finite review geometry.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 338 assertions, 0 failures
```

Focused delta: +2 focused PASS cases, +29 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`.

Adjacent checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 854 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCropPolygonBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php
3 test files, 88 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nonfinite-bbox-currentbase.php
```

The smoke emits `layout_artifact_assigned=true`, `order_artifact_assigned=true`, `finite_order_row_preserved=true`, `source_order_preserved_after_bad_row_drop=true`, `invalid_title_not_promoted=true`, `nonfinite_metadata_json_safe=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected-page artifact selector, supplied layout annotator, supplied orderer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted page marker finite validation, selected range slicing, sparse keyed matching, duplicate keyed reuse prevention, wrapper-list marker extraction, ambiguous array/list rejection, typed result wrapper sanitation, normalized order bboxes, bare bbox-list rows, associative position inference, polygon-only order rows, zero-area row rejection, named object bboxes, layout-payload marker precedence, table named-bbox geometry, table/OCR polygon geometry, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically non-finite supplied layout/order bbox coordinates at the selected pdftext dictionary layout-order assignment boundary.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
