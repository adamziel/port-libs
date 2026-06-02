# markerPDF Indexed DeviceN Soft-Mask Transfer

## Source Truth

Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream image path renders PDF images through PDFium/PIL and converts preview output to RGB. This native PHP slice keeps the parser-side decisions that must happen before a future raster backend: Indexed palette lookup, DeviceN named colorants/tint-transform review, and soft-mask transfer-function alpha mapping.

## Native Behavior

`PdfImageRenderer` now carries soft-mask transfer alpha fields through `indexedAlternateColorantSamplePreview()`:

- `soft_mask_alpha_before_transfer`
- `soft_mask_transfer_applied`
- `soft_mask_transfer_function`

`indexedImageStreamPreviewRows()` also exposes `indexed_alternate_color_space` metadata when an Indexed palette base is Separation or DeviceN, and decoded stream pixels now include named `colorant_tints`, `tint_values`, and soft-mask transfer state fields. Ordinary Indexed RGB rows keep their existing base-component metadata.

## WordPress Smoke

`examples/wordpress-pdf-indexed-devicen-softmask-transfer-currentbase.php` models a PDF image import whose `/Indexed` palette is backed by a `/DeviceN` spot-color alternate space and whose luminosity soft mask uses a Type 2 transfer function. The smoke emits a Gutenberg image block with `data-marker-color-space="Indexed"`, `data-marker-base-color-space="DeviceN"`, `data-marker-soft-mask-transfer-applied="true"`, and transfer alpha `0.75`, while reporting:

- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`
- `executes_pypdfium_or_pil=false`

## Verification

Focused gap covered: prior Indexed/DeviceN image coverage exposed those boundaries separately, but did not carry transfer-alpha fields through Indexed alternate-colorant previews or named DeviceN tint rows through decoded Indexed stream pixels.

Commands run:

```bash
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-indexed-devicen-softmask-transfer-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageCalibratedJbig2SoftMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-pdf-indexed-devicen-softmask-transfer-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . ": valid JSON\n"; }'
git diff --check -- lanes/markerpdf
```

Focused gate results:

```text
2 test files, 562 assertions, 0 failures
6 test files, 720 assertions, 0 failures
```

Expected lane movement:

- PHP behavior tests: `734 -> 736`
- mapped markerPDF/PDF image semantics: `524 -> 525 / 78`

## Non-Overlap

This does not repeat accepted Indexed ICCBased/JBIG2 soft-mask metadata, Indexed soft-mask transfer over DeviceRGB palettes, DeviceN direct-image transfer masks, Indexed Separation soft-mask alpha, inline ImageMask preview rows, JPX/JBIG2 preview-only filter handling, ColorKey mask suppression, DCT CMYK decode planning, calibrated JBIG2 soft-mask review, or named resource color-space soft-mask review. The new behavior is the combined Indexed palette whose base color space is DeviceN, including transfer-alpha review and stream-row colorant propagation.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser, stream-filter decoder, packed sample reader, color-space planner, and `PdfImageRenderer` review surface. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, pypdfium, PIL, PDF actions, decryption, or signature validation.
