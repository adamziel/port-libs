# markerPDF ICC DeviceN Soft-Mask Preview Boundary

Session: `port-dev-markerpdf-image27pdf-20260602T1557Z`
Micro-slice: `image-icc-devicen-softmask-preview-currentbase-20260602T1557Z`
Base accepted HEAD: `47657692317361f6d3d564f3ae90eb5c7da42a7e`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF page images through `marker/pdf/images.py::render_image()` and crop previews through `render_bbox_image()`: PDFium renders at `scale=dpi / 72`, annotation drawing is disabled, and PIL converts the page/crop to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

This native PHP slice keeps that upstream RGB-preview boundary without pypdfium/PIL. The PDF parser-side behavior covered here is the intersection of `/DeviceN` colorants, an indirect ICCBased alternate color space, image `/Decode`, and an image XObject `/SMask` whose stream filters decode through the current object map before alpha is applied.

## Native Behavior Added

`PdfImageRenderer::alternateColorantSamplePreview()` now maps Separation/DeviceN image samples into named colorant tint values before the RGB preview handoff:

- validates sample count against `/Separation` or `/DeviceN` colorant count;
- applies a valid image `/Decode` array with the image `BitsPerComponent`;
- preserves colorant names decoded from PDF name escapes;
- carries the alternate ICCBased profile, alternate component count, tint-transform object, and tint-transform function type as review metadata;
- applies the matching soft-mask sample through the existing soft-mask `/Decode` alpha boundary;
- fails closed for non-alternate color spaces, mismatched samples, mismatched Decode arrays, or missing soft-mask plans.

`examples/wordpress-pdf-icc-devicen-softmask-preview-currentbase.php` models the WordPress review path for a DeviceN image whose alternate color space is indirect ICCBased, whose tint transform is review-only FunctionType 4 metadata, and whose soft mask is decoded through ASCIIHex + Flate filters.

## Evidence

Red-first focused failure after adding the test before the source helper:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
FAIL maps DeviceN ICCBased colorant samples and soft-mask alpha before RGB preview
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::alternateColorantSamplePreview()
1 test files, 189 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 206 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-icc-devicen-softmask-preview-currentbase.php
```

The smoke emits `source_color_space=DeviceN`, colorant tints for `Spot Blue` and `Spot Varnish`, `alternate_color_space=ICCBased`, `alternate_uses_icc_profile=true`, `tint_transform_function_type=4`, `tint_transform_preview_mode=review_only`, `soft_mask_alpha=0.74902`, `decoded_preview_hex=0040FF`, `output_color_mode=RGB`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-icc-devicen-softmask-preview-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally.

Status delta: behavior tests `532 -> 533`; mapped image semantics `379 -> 380 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing alone, soft-mask presence and `/Matte` metadata, soft-mask `/Decode` opacity alone, soft-mask stream-filter decoding alone, Indexed ICCBased/JBIG2 palette metadata, Indexed default Decode/hival clipping, Separation/DeviceN alternate-color metadata, CCITT/JPX preview-only filter metadata, DCTDecode CMYK/YCCK Decode review, or image-filter text-exclusion boundaries.

The new behavior is specifically the combined DeviceN colorant sample preview path where image `/Decode` is applied to named colorants, an ICCBased alternate profile is preserved, and current-object soft-mask alpha is attached before the WordPress RGB image review handoff.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, image Decode planner, soft-mask stream-filter decoder, ICCBased/DeviceN color-space planner, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
