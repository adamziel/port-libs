# markerPDF Calibrated Soft-Mask Review Boundary

Micro-slice: `image-mask-calibrated-softmask-review-currentbase-20260602T1618Z`

Base accepted HEAD: `9192be14c831cb84a6d124eb0733f7e677891025`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes image preview through `marker/pdf/images.py`: page rendering/crops go through PDFium/PIL and are converted to RGB. The native PHP lane does not execute Python, pypdfium, PIL, or model workers, so this slice preserves the parser-side review boundary that must be known before a future raster backend performs RGB conversion.

The PDF parser behavior covered here is CIE-based image color spaces: `/CalGray`, `/CalRGB`, and `/Lab` image dictionaries carry calibration dictionaries, use calibrated default `/Decode` ranges when `/Decode` is omitted, and may still carry an image `/SMask` whose alpha must be applied before RGB preview review.

## Implementation

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now reports calibrated color-space metadata:

- `/CalGray` white point, black point default, gamma, and default Decode `[0 1]`;
- `/CalRGB` white point, black point, gamma array, matrix, dictionary source, and default Decode `[0 1 0 1 0 1]`;
- `/Lab` white point, black point default, range, and default Decode `[0 100 amin amax bmin bmax]`;
- `uses_calibrated_color_space` and `calibrated_color_space` review payloads;
- calibrated default Decode application before RGB preview;
- calibrated notes plus existing soft-mask Decode alpha metadata.

`PdfImageRenderer::calibratedColorSamplePreview()` maps one decoded sample through the plan, preserves named calibrated components (`red/green/blue`, `gray`, or `l/a/b`), and attaches matching soft-mask alpha without rasterization.

`examples/wordpress-pdf-calibrated-softmask-review-currentbase.php` emits WordPress image review metadata for a CalRGB image with an inverted DeviceGray soft mask.

## Verification

Before change baseline:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 206 assertions, 0 failures
```

Focused result after change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 247 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-calibrated-softmask-review-currentbase.php
```

The smoke emits `source_color_space=CalRGB`, `decode_source=default-calibrated`, `uses_default_decode=true`, `soft_mask_alpha=0.74902`, `output_color_mode=RGB`, and all external execution flags false.

Additional checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-calibrated-softmask-review-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, DeviceN/Separation alternate colorant review, Indexed ICCBased/JBIG2 palettes, ImageMask stencil Decode opacity, soft-mask stream-filter decoding, soft-mask Decode opacity alone, DCTDecode CMYK/YCCK Decode review, or image-filter text exclusion. The new behavior is specifically calibrated CIE color-space metadata plus calibrated default Decode sample preview with soft-mask alpha before the Marker RGB preview handoff.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary/value parser, image Decode planner, and soft-mask opacity planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
