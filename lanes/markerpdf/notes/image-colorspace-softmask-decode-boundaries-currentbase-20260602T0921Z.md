# markerPDF Soft-Mask Decode Boundary

Session: `port-dev-markerpdf-image24-20260602T0921Z`
Micro-slice: `image-colorspace-softmask-decode-boundaries-currentbase-20260602T0921Z`
Base accepted HEAD: `cd72f21c7f68cede38b530d69670e4adafc04710`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders image crops through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: the page is rendered with `scale=dpi / 72`, annotations disabled, then converted to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

This native PHP slice keeps that RGB-preview boundary without pypdfium/PIL. It adds the PDF parser decision that a soft-mask image dictionary's own `/Decode` array maps mask samples into opacity before the RGB preview compositor applies the soft mask.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits soft-mask decode metadata:

- resolves direct or indirect soft-mask `/Decode` arrays;
- defaults missing soft-mask `/Decode` to `[0 1]`;
- validates decode-pair count against the soft-mask image color components;
- records zero/max alpha preview values and inverted alpha state;
- preserves component mismatches as review metadata instead of applying them.

`PdfImageRenderer::softMaskSampleOpacity()` maps a decoded soft-mask sample through the mask decode plan into a clamped alpha value for future RGB compositing.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 86 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 100 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-icc-softmask-image-review.php
```

The smoke emits `data-marker-soft-mask-decode="inverted"`, `soft_mask_opacity_zero=1`, `soft_mask_opacity_max=0`, `soft_mask_decode_applied_before_rgb=true`, and `executes_pypdfium_or_pil=false`.

Syntax and consistency checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-icc-softmask-image-review.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, soft-mask presence and `/Matte` metadata, base image `/Decode` sample mapping, `/ImageMask` stencil decode opacity, DCTDecode CMYK Adobe-transform planning, image-filter text exclusion, inline-image payload boundaries, or the latest xref/object-stream conflict work. It covers only the soft-mask image dictionary's `/Decode` alpha boundary.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser and `PdfImageRenderer` review planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, or raster rendering.
