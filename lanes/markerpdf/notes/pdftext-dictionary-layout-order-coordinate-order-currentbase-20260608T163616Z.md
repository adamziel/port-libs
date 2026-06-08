# markerPDF pdftext dictionary layout/order coordinate-order boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T163616Z`

Accepted base: `5b8ea24af48dcb3ad921ab7b94f34569273f4087`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so the native PHP path starts with selected pdftext page dictionaries.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order handoff.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` consume selected-page image-space bboxes. In this no-GPU lane, supplied layout/order artifacts stand in for model output.
- The existing native table handoff already treats `bbox_order`/`bbox_coordinate_order`/`bbox_format`/`coordinate_order` values such as `x1_x2_y1_y2` as coordinate-order metadata for four-number bbox lists.

## Implemented Behavior

- `LayoutOrderer` now normalizes `image_bbox` and order-row `bbox` four-number lists through supplied coordinate-order metadata before image-extent validation, normalized-bbox rejection, overlap matching, and stored order metadata.
- `LayoutAnnotator` now applies the same coordinate-order normalization for supplied layout `image_bbox` and layout-row `bbox` values before WordPress block typing.
- Row-level coordinate-order metadata remains authoritative, while shared result-level `bbox_order`/`bboxes_bbox_order` style metadata is inherited by layout/order rows that do not repeat the same key.
- Supported aliases are bounded to the existing table vocabulary: `[x1,y1,x2,y2]`, `[x1,x2,y1,y2]`, `[y1,x1,y2,x2]`, and `[y1,y2,x1,x2]` labels plus common long-form names.
- Added a focused test file with two PASS cases: direct pdftext order assignment geometry and WordPress supplied-document layout heading promotion.
- Added `wordpress-pdftext-dictionary-layout-order-coordinate-order-currentbase.php` as the WordPress smoke.

## Red-First Evidence

After adding the focused regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes supplied order coordinate-order rows before pdftext dictionary assignment
Values are not identical
Expected: [0.0, 0.0, 612.0, 792.0]
Actual: NULL
FAIL normalizes supplied layout coordinate-order rows before WordPress pdftext import
String does not contain '# Coordinate Order Title'

1 test files, 9 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-coordinate-order-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-coordinate-order-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes supplied order coordinate-order rows before pdftext dictionary assignment
PASS normalizes supplied layout coordinate-order rows before WordPress pdftext import

1 test files, 16 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
5 test files, 1783 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-coordinate-order-currentbase.php
```

It exits 0 and emits `layout_coordinate_order_normalized=true`, `order_coordinate_order_normalized=true`, `heading_promoted=true`, `body_preserved_as_paragraph=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

JSON validation:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

Focused delta: +2 focused PASS cases, +16 assertions in the new focused test file, and +1 WordPress smoke. `lane-status.json` moves `phpPass` from `3303` to `3305` and `wordpressScenarios` from `2691` to `2692`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected artifact matching, layout annotation, reading-order assignment, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected page-range slicing, sparse/keyed artifact matching, string/decimal/list/page-map markers, direct payload wrappers, typed JSON/direct envelopes, singleton key mismatch guards, direct-key marker conflict, metadata sibling selection, scalar sidecars, raw page artifacts, named bboxes, point pairs, polygon aliases, normalized bbox scaling, zero-overlap/zero-area/non-finite rejection, table geometry coordinate-order handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically supplied pdftext layout/order coordinate-order metadata before selected-page order assignment and WordPress block typing.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or table/equation handoff edges.
