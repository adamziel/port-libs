# markerPDF Image Soft-Mask ColorKey ICC Conflict

Session: `port-dev-markerpdf-image35pdf-20260602T173108Z`
Micro-slice: `image-softmask-colorkey-icc-conflict-rebase-currentbase-20260602T173108Z`
Base accepted HEAD: `f6a226052136abadc56f7b8d8b89c4b84d502d1b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page and crop images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders at `dpi / 72`, annotations are disabled, and PIL converts output to RGB before `marker/images/extract.py::extract_page_images()` inserts Markdown image spans.

This native PHP slice keeps that RGB-preview boundary without pypdfium/PIL. The PDF parser-side conflict is a parent image that has an ICCBased color space, a color-key `/Mask` array, and an image `/SMask`: the raw ColorKey ranges remain review metadata, but the present soft mask is the effective alpha source and suppresses applying the ColorKey mask.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/PDF32000_2008.pdf

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits `color_key_mask_suppressed_by_soft_mask` when a color-key `/Mask` array is present with a real `/SMask`. It still records the raw ColorKey ranges and ICC profile metadata, but no longer marks the ColorKey mask as applied.

`PdfImageRenderer::colorKeyMaskSamplePreview()` fails closed when the plan says the ColorKey mask is suppressed by a soft mask, preventing callers from accidentally composing two alpha sources.

`examples/wordpress-pdf-image-softmask-colorkey-icc-conflict-currentbase.php` models the WordPress media-review path for an ICCBased image whose raw sample matches the ColorKey range, while the effective alpha still comes from the soft mask.

## Evidence

Current-base pre-fix probe showed the bug:

```text
color_key_applied=true
alpha_output_mode=soft_mask_composited_to_rgb_preview
notes included color_key_mask_applied_before_rgb_conversion
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 361 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-softmask-colorkey-icc-conflict-currentbase.php
```

The smoke emits `data-marker-color-key-suppressed="true"`, `data-marker-color-key-preview-blocked="true"`, `data-marker-alpha-source="soft-mask"`, ICC profile metadata, soft-mask alpha, and `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-softmask-colorkey-icc-conflict-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Status delta: behavior tests `604 -> 605`; mapped image semantics `438 -> 439 / 78`.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing alone, soft-mask `/Decode` opacity alone, soft-mask stream-filter decoding, ColorKey raw-sample comparison alone, Indexed ICCBased/JBIG2 soft-mask palettes, Indexed default Decode clipping, DeviceN/Separation alternate colorant review, DCTDecode CMYK/YCCK Decode review, image-filter text exclusion, or inline image review planning.

The bounded behavior is specifically the conflict where ICC profile review, image Decode metadata, raw ColorKey ranges, and soft-mask alpha are all present, and the soft mask suppresses ColorKey application before WordPress RGB media review.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, image Decode planner, ICCBased color-space metadata, soft-mask alpha planner, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
