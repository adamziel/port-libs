# markerPDF Image XObject Rotate/UserUnit Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T083457Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T083457Z`
Base accepted HEAD: `3fbce78dff945c4108221de18bd13fb2feb4f8f0`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable text extraction from image rendering:

- `marker/pdf/extract_text.py` routes text through pdftext/PDFium dictionaries before Marker block conversion.
- `marker/pdf/images.py` renders page/bbox images through PDFium, disables annotation rendering for extraction crops, converts to RGB, and returns image data outside the text pipeline.

This no-GPU PHP slice maps the native parser-side boundary needed before any future raster backend: Image XObject review rows now preserve raw PDF user-space placement bboxes and also expose marker-app-style display-space bboxes after inherited page `/Rotate`, page-local `/UserUnit`, and the effective page crop/media boundary.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now:

- resolves inherited page `/Rotate` and page-local positive `/UserUnit` for image placement review rows;
- records `page_rotation`, `page_rotation_source`, `page_user_unit`, `page_user_unit_source`, axis-swap and UserUnit display flags, plus `page_display_size`;
- emits `invocation_display_bboxes`, `invocation_visible_display_bboxes`, `image_display_bbox`, and `image_visible_display_bbox` alongside existing raw PDF `invocation_bboxes` and `image_unit_bbox`;
- keeps existing page crop clipping, Form XObject traversal, optional-content visibility, stream-filter review, and payload-exclusion behavior unchanged.

The focused fixture has one inherited `/Rotate 90` page with page-local `/UserUnit 2`, a fully visible Image XObject, and a crop-clipped Image XObject whose raw display bbox extends beyond the rendered page while visible display bbox is clipped to the 400x320 preview boundary.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps rotated UserUnit page geometry for image XObject display review
Values are not identical
Expected: 90
Actual: NULL
PHP Warning:  Undefined array key "page_rotation" ...
1 test files, 573 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-rotation-userunit-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-rotation-userunit-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 604 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
2 test files, 1232 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-rotation-userunit-currentbase.php
```

The smoke exits 0 and emits `page_rotation=90`, `page_user_unit=2`, `page_display_size={"width":400,"height":320}`, `rotated_image_display_bbox=[40,20,80,100]`, `clipped_image_raw_display_bbox=[380,280,460,380]`, `clipped_image_visible_display_bbox=[380,280,400,320]`, `display_geometry_review_only=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `1627 -> 1628` pass / `0` fail.
- WordPress scenarios: `1502 -> 1503`.
- Focused image assertion count: red-first `573` assertions / `1` failure, then `604` assertions / `0` failures.
- Mapped upstream denominator: unchanged; this refines the already mapped Image XObject rendering/review boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, page crop clipping, content `re W` clipping, optional content, exact object generation, SMask/Mask image stream metadata, alternates, metadata streams, named ColorSpace resources, ExtGState transparency review, inline-image parsing, DCT/CCITT/JPX/JBIG2 preview filters, Form-resource image discovery, marker-app page preview sizing, or rotated/UserUnit link/markup annotation geometry. The bounded behavior is specifically Image XObject review display bboxes after page `/Rotate` and `/UserUnit`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page-tree lineage resolver, numeric operand resolver, content tokenizer, Form XObject traversal, stream decoder, image review rows, and WordPress smoke path. Full rendered pixel parity remains dependency-gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and other external PDF tools were not run.
