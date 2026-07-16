# markerPDF ICC Soft-Mask Decode Review

Session: `port-dev-markerpdf-image28pdf-20260602T1608Z`
Micro-slice: `image-icc-softmask-decode-review-currentbase-20260602T1608Z`
Base accepted HEAD: `1556f4d4531f91f7e52406c68e4d138258622c73`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders image crops through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders with `scale=dpi / 72`, annotations disabled, and PIL converts page/crop output to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf

The native PDF boundary for this slice is before RGB compositing: a soft mask used as alpha must be a one-channel grayscale image, and a soft-mask `/Matte` array is only actionable when its component count matches the parent image color space. This matters for ICCBased parent images because `/Matte` belongs to the parent image color components, while the soft-mask sample remains a grayscale alpha source.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits:

- `soft_mask_is_grayscale` and `soft_mask_color_space_supported`;
- `soft_mask_matte` component-count review metadata;
- review-only handling for non-grayscale soft masks instead of applying them as alpha;
- matte-unblend gating only when the soft mask is grayscale and `/Matte` matches the parent image components;
- a `soft_mask_review_only_rgb_preview` alpha output mode for invalid color soft masks.

`PdfImageRenderer` also stops computing scalar soft-mask opacity for multi-component mask color spaces. Valid one-channel ICCBased soft masks continue to apply their `/Decode` array before RGB preview compositing.

`examples/wordpress-pdf-icc-softmask-image-review.php` now emits the same review flags for a valid DeviceGray soft mask and a review-only non-grayscale mask with a bad `/Matte` length.

## Evidence

Intermediate focused failure after adding the test before completing the planner guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 195 assertions, 1 failures
FAIL flags non grayscale soft masks and ICC matte component mismatches before RGB preview
Image sample component count does not match Decode ranges.
```

Passing focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 217 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-icc-softmask-image-review.php
```

The smoke emitted `soft_mask_is_grayscale=true`, `soft_mask_matte.matches_image_components=true`, `review_only_soft_mask_is_grayscale=false`, `review_only_soft_mask_applied_before_rgb=false`, `review_only_soft_mask_matte.matches_image_components=false`, `review_only_soft_mask_notes=["icc_profile_color_space","soft_mask_color_space_not_grayscale","soft_mask_matte_component_mismatch"]`, `data-marker-soft-mask-alpha-source="grayscale"`, `data-marker-review-only-soft-mask="true"`, and execution flags false for Python/models, external PDF tools, pypdfium, and PIL.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-icc-softmask-image-review.php
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile metadata, basic soft-mask presence, `/SMask /None`, soft-mask `/Decode` opacity, soft-mask stream-filter decoding, Indexed default Decode, Indexed ICC/JBIG2 soft-mask palette metadata, base image `/Decode`, `/ImageMask` stencil decode, DCTDecode CMYK/YCCK Decode review, Separation/DeviceN alternate-color preview, or image-filter text-exclusion boundaries. The new behavior is specifically the ICC image review boundary that separates grayscale alpha masks from review-only non-grayscale masks and validates `/Matte` against the parent ICC color components.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser and `PdfImageRenderer` preview planner. Full live raster parity remains dependency-gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, raster rendering, pypdfium, or PIL.
