# markerPDF pdftext dictionary layout/order normalized layout boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T143729Z`

Accepted base: `2079d2af8e75259ff2c2e73aeb5c9816f679fef2`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so the native PHP path starts with selected pdftext page dictionaries.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order handoff.
- `marker/layout/layout.py::surya_layout()` zips layout predictions to selected pages, and `marker/layout/order.py::surya_order()` then zips order predictions to those pages before Markdown finalization. In this no-GPU lane, supplied layout/order artifacts stand in for model output and must preserve the same selected-page/image-coordinate boundary.

## Implemented Behavior

- `LayoutAnnotator` now expands normalized supplied layout bboxes against the layout `image_bbox` before the existing image-to-page rescale.
- Selected pdftext dictionary pages whose supplied layout artifacts use normalized `[0..1]` geometry now receive the intended `Title` and `Text` block types before WordPress Markdown finalization.
- Stored layout review metadata still preserves the supplied normalized geometry and excludes raw adapter payload text.
- Added a focused WordPress-facing regression to `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdftext-dictionary-layout-order-normalized-layout-currentbase.php` as the local WordPress smoke.

## Red-First Evidence

After adding the focused regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rescales normalized supplied layout boxes before WordPress pdftext dictionary import
String does not contain '# Normalized Layout Title'
Haystack: Normalized layout title Normalized layout body remains paragraph.

1 test files, 104 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-normalized-layout-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-normalized-layout-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rescales normalized supplied order boxes before pdftext dictionary layout assignment
PASS rescales normalized supplied layout boxes before WordPress pdftext dictionary import
PASS keeps nested pdftext page payload markers out of trusted document-page order metadata
PASS uses bbox-list order rows as ordered geometry before pdftext dictionary layout assignment
PASS infers missing order positions from bbox dictionary row order before pdftext layout assignment
PASS prefers trusted metadata over copied source-page payload markers before pdftext layout order assignment
PASS keeps copied source pdftext payloads fallback-only for WordPress layout and order artifacts
PASS unwraps typed order-result payload wrappers before selected pdftext layout assignment
PASS unwraps typed layout and order result wrappers for WordPress supplied document imports

1 test files, 108 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
5 test files, 1249 assertions, 0 failures
```

Focused delta: +1 focused PASS case and +11 assertions over the previous 97-assertion focused file baseline. The red-first run had 104 assertions because it stopped at the first failing assertion in the new case.

The WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-normalized-layout-currentbase.php
```

It emits `normalized_layout_bboxes_scaled_for_block_types=true`, `body_preserved_as_paragraph=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range artifact selector, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected page-range slicing, sparse/keyed artifact matching, string/decimal/list page-marker normalization, wrapper-list markers, typed result-wrapper unwrapping, copied source/pdftext payload exclusion, normalized order bbox scaling, malformed/zero-area order-row rejection, duplicate/conflicting identity guards, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically normalized supplied layout geometry before WordPress block typing on selected pdftext dictionary pages.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or table/equation handoff edges.
