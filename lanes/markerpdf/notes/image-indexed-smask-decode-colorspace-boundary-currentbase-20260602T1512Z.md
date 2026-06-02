# markerPDF Indexed Decode Soft-Mask Color-Space Boundary

Session: `port-dev-markerpdf-image23pdf-20260602T1512Z`
Micro-slice: `image-indexed-smask-decode-colorspace-boundary-currentbase-20260602T1512Z`
Base accepted HEAD: `c42f7d5b86b2747133272f1f26b4e3d9fda2ed6b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF page images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders with `scale=dpi / 72`, annotation drawing disabled, and PIL converts the page/crop to RGB before `marker/images/extract.py::extract_page_images()` inserts Markdown image spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf

The relevant PDF parser boundary is the Indexed color-space lookup: an Indexed color space uses sample values as palette indices from `0` through `hival`, and image `/Decode` maps raw samples into the color-space component before rendering. For an Indexed image, the missing `/Decode` default is therefore `[0 hival]`, not a missing review decision.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now defaults a missing image `/Decode` on Indexed color spaces to `[0 hival]` with source `default-indexed`.

`PdfImageRenderer::indexedSamplePreview()` now:

- applies valid image `/Decode` before palette lookup;
- rounds and clips decoded palette indices to the declared Indexed `hival`;
- expands the selected palette row into normalized base color components;
- optionally maps the matching soft-mask sample through the soft-mask Decode plan;
- keeps all output as review metadata for the existing RGB preview boundary.

`examples/wordpress-pdf-indexed-smask-decode-colorspace-boundary.php` models a WordPress import review for an Indexed image without explicit `/Decode`, an inverted soft mask, and a second explicit Decode that clips beyond `hival`.

## Evidence

Red-first focused failure after adding the test before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 165 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 189 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-indexed-smask-decode-colorspace-boundary.php
```

The smoke emits `default_decode_source=default-indexed`, `last_palette_index=2`, `last_soft_mask_alpha=1`, `explicit_decode_clamped_to_hival=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-indexed-smask-decode-colorspace-boundary.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, explicit base image `/Decode` sample mapping, ImageMask stencil opacity, soft-mask Decode opacity, soft-mask stream-filter decoding, Indexed ICC/JBIG2 palette metadata, Separation/DeviceN alternate-color review, DCTDecode CMYK/YCCK Decode review, or image-filter text-exclusion boundaries. The new behavior is specifically Indexed color-space default Decode plus decoded-index clipping and soft-mask alpha preview composition.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF dictionary/value parser and `PdfImageRenderer` review planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
