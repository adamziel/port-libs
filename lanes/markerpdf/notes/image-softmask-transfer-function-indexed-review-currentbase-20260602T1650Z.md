# markerPDF Soft-Mask Transfer Function Indexed Review

Session: `port-dev-markerpdf-image32pdf-20260602T1650Z`
Micro-slice: `image-softmask-transfer-function-indexed-review-currentbase-20260602T1650Z`
Base accepted HEAD: `979eed90b32f605444fc1fff5af2fb4f932d25b8`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF page and crop images through `marker/pdf/images.py::render_image()` / `render_bbox_image()`: PDFium renders with `scale=dpi / 72`, annotation drawing disabled for image extraction, and PIL converts the output to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf

The native PHP boundary stays review-only for raster output, but now records the PDF operands that PDFium would apply for a soft-mask dictionary: `/S`, transparency group `/G`, group `/Group /CS`, Indexed palette lookup, `/BC`, and `/TR`.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now detects soft-mask dictionaries such as `<< /Type /Mask /S /Luminosity /G ... /BC [...] /TR ... >>` separately from image XObject `/SMask` streams.

The new review metadata records:

- soft-mask subtype (`Alpha` or `Luminosity`), source object, group object, group subtype, BBox, isolation/knockout flags, and backdrop color;
- transparency-group color-space details, including Indexed palette bytes and hival metadata;
- transfer-function source, object number, function type, domain/range, C0/C1, exponent, output count, and preview mode;
- bounded sample application for `/Identity` and FunctionType 2 one-output transfer functions through `softMaskTransferSampleOpacity()`.

`examples/wordpress-pdf-softmask-transfer-indexed-review-currentbase.php` emits a WordPress image block review for an Indexed image with a Luminosity soft-mask group using an Indexed transparency color space and a Type 2 transfer function.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 320 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-softmask-transfer-indexed-review-currentbase.php
```

The smoke emits `source_color_space=Indexed`, `palette_index=2`, `soft_mask_group.subtype=Luminosity`, `soft_mask_group.group_color_space=Indexed`, `soft_mask_transfer_function.preview_mode=type2_exponential`, `transfer_alpha_for_quarter_sample=0.75`, `output_color_mode=RGB`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-softmask-transfer-indexed-review-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally after the final status update.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted image XObject soft-mask Decode opacity, soft-mask stream-filter decoding, ICCBased image soft-mask metadata, Indexed default Decode/hival clipping, Indexed ICCBased/JBIG2 image review, inline Indexed/JBIG2/ImageMask review, Separation/DeviceN tint review, DCT CMYK Decode review, or generic image-filter text-exclusion boundaries.

The new behavior is specifically the soft-mask dictionary transfer-function path where a transparency group uses an Indexed color space and `/TR` is captured/applied as review metadata before the RGB preview handoff.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser and `PdfImageRenderer` review planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools.
