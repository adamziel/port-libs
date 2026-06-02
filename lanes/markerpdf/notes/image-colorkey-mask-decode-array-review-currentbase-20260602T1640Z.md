# markerPDF Image ColorKey Mask Decode Review

Session: `port-dev-markerpdf-image31pdf-20260602T1640Z`
Micro-slice: `image-colorkey-mask-decode-array-review-currentbase-20260602T1640Z`
Base accepted HEAD: `2c21071f7e9064c624f93392d27c864177463373`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page and crop images through `marker/pdf/images.py::render_image()` / `render_bbox_image()`: PDFium renders at `dpi / 72`, annotations are disabled, and PIL converts the result to RGB before `marker/images/extract.py::extract_page_images()` inserts Markdown image spans.

This slice keeps that RGB preview boundary in native PHP without pypdfium/PIL. The PDF parser-side behavior covered here is color-key masking: parent image `/Mask [min max ...]` arrays compare raw image samples before `/Decode`, while `/Decode` still maps surviving samples into color-space values before RGB review.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits `color_key_mask` metadata for color-key `/Mask` arrays, including raw-sample range pairs, expected component counts, component mismatch state, and the explicit note that matching samples become transparent before `/Decode`-adjusted RGB preview values are reviewed.

`PdfImageRenderer::colorKeyMaskSamplePreview()` compares raw samples against the color-key ranges, returns alpha `0.0` for matching transparent samples and `1.0` for nonmatching opaque samples, then applies a valid base image `/Decode` array for the decoded RGB-review components.

`examples/wordpress-pdf-image-colorkey-mask-decode-currentbase.php` models the WordPress media-review path for a DeviceRGB image with an inverted red `/Decode` component and a three-component ColorKey mask.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 276 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 297 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-colorkey-mask-decode-currentbase.php
```

The smoke emits `data-marker-color-key-mask="true"`, `data-marker-mask-compares="raw-samples-before-decode"`, transparent alpha `0`, opaque alpha `1`, Decode-after-mask `true`, `executes_pypdfium_or_pil=false`, and `executes_external_pdf_tools=false`.

Status delta: behavior tests `564 -> 565`; mapped image semantics `404 -> 405 / 78`.

## Non-Overlap

This does not repeat accepted base image `/Decode`, `/ImageMask` stencil opacity, soft-mask `/Decode`, soft-mask stream-filter decoding, ICCBased/Calibrated/DeviceN/Indexed color-space review, Indexed default Decode and hival clipping, DCTDecode CMYK/YCCK Decode review, or image-filter text exclusion.

The new behavior is specifically the ColorKey `/Mask` array review path where transparency is decided from raw image samples before a valid image `/Decode` array feeds RGB preview metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, image Decode planner, and `PdfImageRenderer` review-planning path. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
