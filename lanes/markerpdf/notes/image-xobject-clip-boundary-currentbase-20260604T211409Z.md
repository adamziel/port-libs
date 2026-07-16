# markerPDF Image XObject Clip Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260604T211409Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260604T211409Z`
Base accepted HEAD: `1480bbab70b54431a9debcd67786a4a112caa532`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from rendered page/image output:

- `marker/pdf/extract_text.py` uses `pdftext.extraction.dictionary_output` for text blocks and page geometry.
- `marker/pdf/images.py` renders PDF pages with pypdfium, disables annotation drawing, converts the result to RGB, and crops rendered page images for image/equation regions.

Source URLs inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

This native PHP no-GPU slice maps the parser-side boundary needed before any future raster backend: Image XObject placement review now honors rectangular clipping paths so WordPress media review can distinguish raw CTM placement from actually painted visible bounds.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records clipping metadata for image `Do` invocations:

- tracks `re W/W* n` rectangular clip paths alongside q/Q graphics-state restore and cm transforms;
- records raw `invocation_bboxes` from the Image XObject CTM and separate `invocation_visible_bboxes` after clipping;
- records `invocation_clip_bboxes`, unioned `image_visible_bbox`, `painted_invocation_count`, `clip_reduces_painted_bbox`, `clip_excludes_image`, and `clip_excluded_invocation_count`;
- propagates active clip rectangles into nested Form XObject image scans and intersects them with Form XObject `/BBox`;
- preserves all existing review-only image metadata and keeps raster stream payload bytes out of visible WordPress text.

The focused fixture covers a partially clipped page Image XObject, a fully clip-excluded page Image XObject, and a nested Form XObject image clipped in form-local coordinates after the caller matrix is applied.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL applies rectangular clipping paths to image XObject placement review
Values are not identical
Expected: [[10.0,10.0,40.0,30.0]]
Actual: NULL
1 test files, 159 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps inherited page image XObject resources as review-only current-base metadata
PASS keeps invoked image XObject payload bytes out of WordPress text extraction
PASS maps image XObjects invoked inside Form XObject resources as review-only metadata
PASS maps image XObjects invoked by resource-less Form XObjects through inherited page resources
PASS counts optional-content-hidden image XObject invocations as unpainted review metadata
PASS records image XObject invocation CTM placement for WordPress media review
PASS applies rectangular clipping paths to image XObject placement review
PASS reports encrypted image XObject documents as fail-closed empty reviews

1 test files, 189 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted `image_xobject_count=3`, `invoked_image_xobject_count=1`, `first_invocation_bbox=[136,722,648,850]`, `first_image_visible_bbox=[200,722,456,786]`, `first_clip_reduces_painted_bbox=true`, `first_clip_excludes_image=false`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `1092 -> 1093` pass / `0` fail.
- Focused assertion count for `PdfImageXObjectBoundaryCurrentBaseTest.php`: red-first `159` assertions with `1` failure, then `189` assertions / `0` failures.
- WordPress scenario count: `1092 -> 1093`.
- Mapped upstream denominator: unchanged; this refines the already mapped image XObject placement/rendering boundary.

## Non-Overlap

This does not repeat accepted page resource Image XObject metadata, nested Form XObject image discovery, resource-less Form XObject inheritance, optional-content image visibility, CTM-only placement bboxes, inline image parsing, DCT/CCITT/JPX/JBIG2 preview-only filters, soft-mask/color-space preview rows, or Form XObject text extraction. The new behavior is specifically rectangular clipping applied to image painted-bounds review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page resource resolver, content tokenizer, existing rectangular clip-path helpers, stream decoder, image stream recognizer, and WordPress smoke path. Full upstream raster parity remains dependency-gated on PDFium/pypdfium and PIL image conversion; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and external PDF tools were not run.
