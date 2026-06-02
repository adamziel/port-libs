# markerPDF Image Soft-Mask ColorKey JPX Current Base

Session: `port-dev-markerpdf-image50-20260602T2023Z`
Micro-slice: `image-softmask-colorkey-jpx-currentbase`
Base accepted HEAD: `1d0255efc342976ccd01090ebca142bc846d342a`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes image rendering through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders the page/crop, annotations stay disabled, and PIL converts the result to RGB. The native PHP port keeps this as a metadata/review boundary instead of executing PDFium, PIL, Python models, or external PDF tools.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://pdf-issues.pdfa.org/32000-2-2020/clause08.html

The PDF boundary for this slice is JPX `/SMaskInData` value validation. Values `1` and `2` keep the accepted embedded-opacity precedence over ColorKey `/Mask` and external `/SMask`. Invalid JPX `/SMaskInData` values are review-only metadata and must not be treated as embedded alpha.

## Native Behavior

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now only marks JPX embedded opacity active when `/SMaskInData` resolves to valid value `1` or `2`.

Invalid JPX `/SMaskInData` values now:

- set `valid_value=false` and `review_only=true`;
- do not set `uses_embedded_soft_mask`;
- do not suppress an otherwise valid ColorKey `/Mask` when no real soft mask is present;
- do not ignore an explicit external `/SMask`, so the external grayscale mask remains authoritative.

This fixes the current-base red observation where `/SMaskInData 9` was treated as embedded opacity and blocked ColorKey preview.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php
1 test files, 86 assertions, 0 failures
```

Adjacent image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
4 test files, 677 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-colorkey-softmask-jpx-currentbase.php
```

The smoke emits the accepted valid JPX embedded-opacity review fields and the new invalid-value guard: `invalid_smask_in_data.valid_value=false`, `invalid_smask_in_data_color_key_applied=true`, and `invalid_smask_in_data_alpha_mode=color_key_mask_composited_to_rgb_preview`.

Status delta: focused behavior tests `773 -> 775` pass / `0` fail.

## Non-Overlap

This does not repeat accepted ColorKey raw-sample comparison, valid JPX `/SMaskInData` embedded opacity precedence, inline JPX payload delimiter handling, inline JPX external soft-mask Decode metadata, Indexed JPX preview-only soft-mask streams, soft-mask transfer functions, DeviceN JPX transfer-mask review, DCTDecode CMYK Decode, or generic image filter exclusion.

The new behavior is specifically invalid JPX `/SMaskInData` value validation and fallback to ColorKey or external `/SMask` behavior.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary/value resolver, JPX filter review metadata, ColorKey mask planner, external soft-mask planner, inline image review path, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL/PDFium or a future native JPX raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, OCR, or external PDF tools.
