# markerPDF Image Decode And Stencil Preview Boundary

Session: `port-dev-markerpdf-imagecolor15-20260602T080321Z`
Micro-slice: `image-colorspace-mask-decode-preview-boundaries-20260602T080321Z`
Base accepted HEAD: `60eb156a5f6e6a58dc5d1860263a85a4c543e8e3`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders the page or crop with `scale=dpi / 72`, annotations disabled for extraction crops, and PIL converts the result to RGB before Marker inserts image Markdown through `marker/images/extract.py::extract_page_images()`.

This native PHP slice keeps that same RGB-preview boundary without running pypdfium/PIL. It records two PDF parser decisions that must happen before a future raster backend can produce the same RGB preview:

- Base image `/Decode` arrays map decoded sample values into color-space component values before RGB conversion.
- `/ImageMask true` stencil images use a one-component `/Decode` array, defaulting to `[0 1]`, to decide per-sample opacity before compositing into the RGB preview.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits:

- `image_decode` metadata for explicit `/Decode` arrays, including ranges, expected component count, inversion, and component mismatch state.
- `image_mask` metadata for `/ImageMask true` stencils, including default or explicit decode arrays and opacity for zero/one samples.
- `image_decode_applied_before_rgb`, `image_decode_component_mismatch`, and `image_mask_applied_before_rgb` flags.

`PdfImageRenderer::imageSampleDecodeValues()` maps decoded samples through a valid `/Decode` plan, and `imageMaskSampleOpacity()` maps one-bit stencil samples to opacity. Component-count mismatches remain reviewable metadata and throw if applied.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 64 assertions, 2 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 85 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
59 test files, 2619 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-decode-stencil-preview.php
```

The smoke emits `data-marker-image-decode="true"`, `data-marker-image-mask="true"`, `data-marker-image-mask-decode="inverted"`, `decoded_sample=[1,0.5019607843137255,1]`, `image_mask_opacity_zero=1`, `image_mask_opacity_one=0`, and `executes_pypdfium_or_pil=false`.

Syntax checks passed for:

- `lanes/markerpdf/src/PdfImageRenderer.php`
- `lanes/markerpdf/tests/PdfImageRendererTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-image-decode-stencil-preview.php`

`git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This avoids the queued `colorkey6` color-key `/Mask` plus soft-mask `/Decode` handoff and does not repeat accepted ICCBased profile/soft-mask metadata, DCTDecode CMYK Adobe-transform planning, text-extractor image-filter exclusions, inline image payload boundaries, or stream-filter error-boundary behavior. It covers base image `/Decode` sample mapping and `/ImageMask` stencil decode opacity on the preview-planning path.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser and `PdfImageRenderer` review planner. Full live preview parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, or raster rendering.
