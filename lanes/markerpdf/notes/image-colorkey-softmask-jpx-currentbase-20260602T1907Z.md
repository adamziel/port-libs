# markerPDF Image ColorKey Soft-Mask JPX Current Base

Session: `port-dev-markerpdf-image44pdf-20260602T1907Z`
Micro-slice: `image-colorkey-softmask-jpx-currentbase`
Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders at `dpi / 72`, annotations are disabled, PIL converts to RGB, and `marker/images/extract.py::extract_page_images()` inserts image Markdown spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://pdf-issues.pdfa.org/32000-2-2020/clause08.html

The PDF boundary for this slice is `/SMaskInData` on JPXDecode image XObjects and inline images. Nonzero values mean opacity is packaged with the JPEG 2000 samples; if an external `/SMask` is also present, it is invalid for this boundary and stays ignored as review metadata. A ColorKey `/Mask` array must not be applied as the effective alpha source when embedded JPX opacity is present.

## Native Behavior

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits `jpx_soft_mask_in_data` metadata for `/SMaskInData`, including zero/nonzero state, value validity, JPX filter applicability, embedded opacity mode, preblended-matte mode, and whether an external `/SMask` was ignored.

When `JPXDecode` and nonzero `/SMaskInData` are present:

- ColorKey `/Mask` ranges are preserved as raw review metadata but are not applied.
- `colorKeyMaskSamplePreview()` remains fail-closed through the existing soft-mask suppression guard.
- External `/SMask` image streams are not decoded or treated as current alpha.
- `alpha_output_mode` becomes `jpx_embedded_soft_mask_review_only_rgb_preview`.

Zero-valued `/SMaskInData` and non-JPX `/SMaskInData` do not suppress ColorKey masks.

`PdfImageRenderer::inlineImageReviewPlan()` now propagates inline JPX embedded soft-mask fields into `inline_image` metadata.

## Evidence

Focused tests:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php
1 test files, 55 assertions, 0 failures
```

Adjacent image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
3 test files, 578 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-colorkey-softmask-jpx-currentbase.php
```

The smoke emits `preview_only_filters=["JPXDecode"]`, `native_raster_decode=false`, `smask_in_data.value=2`, `external_soft_mask_ignored=true`, `color_key_mask_suppressed_by_soft_mask=true`, `color_key_preview_blocked=true`, and all Python/PIL/external-tool execution flags false.

Status delta: behavior tests `679 -> 682`; mapped semantics `493 -> 494 / 78`.

## Non-Overlap

This does not repeat accepted ColorKey raw-sample comparison, ICC soft-mask ColorKey conflict, inline JPX payload delimiter handling, inline JPX external soft-mask Decode metadata, Indexed JPX preview-only soft-mask streams, soft-mask transfer functions, DeviceN/ICCBased soft-mask sample rows, DCTDecode CMYK Decode, or generic image filter exclusion.

The bounded behavior is specifically JPX `/SMaskInData` embedded opacity precedence over ColorKey `/Mask` and invalid external `/SMask`, including zero/non-JPX non-suppression.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary/value parser, filter-name review metadata, ColorKey mask planner, soft-mask planner, inline image review path, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL/PDFium or a future native JPX raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, OCR, or external PDF tools.
