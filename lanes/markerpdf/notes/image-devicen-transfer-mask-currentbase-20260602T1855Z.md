# markerPDF DeviceN Transfer-Mask Current Base

Micro-slice: `image-deviceN-transfer-mask-currentbase`
Session: `port-dev-markerpdf-image43pdf-20260602T1855Z`
Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/images.py`: `render_image()` renders through pypdfium, then converts to RGB; `render_bbox_image()` crops and converts to RGB. The native PHP lane keeps that RGB preview boundary without executing Python, pypdfium, PIL, models, or external PDF tools.
- PDF soft-mask dictionaries derive mask values from group alpha or luminosity and pass the alpha/luminosity value through `/TR`; `/Identity` is the default transfer function and supported Type 2 functions map one sample in `[0, 1]` to a clipped output alpha in `[0, 1]`.

## Behavior

This slice adds the missing DeviceN/Separation sample-preview boundary where a `/DeviceN` image uses a soft-mask dictionary:

- `/ColorSpace [/DeviceN ...]` colorant samples still map through image `/Decode` into named tint values.
- `/SMask << /S /Luminosity /G ... /TR ... >>` now applies a supported `/TR` FunctionType 2 transfer function to the mask alpha before RGB review metadata.
- The preview keeps the DeviceN tint transform review-only; no tint-transform PostScript code, Python worker, pypdfium, PIL, or external raster tool executes.

## Red-First Evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Failed before the source change:

`1 test files, 490 assertions, 1 failures`

Failure:

`Soft mask plan must describe a present image mask.`

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  - Passed after fix: `1 test files, 508 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php`
  - Final focused image gate passed: `2 test files, 541 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-image-devicen-transfer-mask-currentbase.php`
  - Smoke passed and emits `source_color_space=DeviceN`, `soft_mask_subtype=Luminosity`, `soft_mask_alpha_before_transfer=0.25`, `soft_mask_alpha=0.75`, `soft_mask_transfer_applied=true`, `output_color_mode=RGB`, and all execution flags false.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
  - Passed: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageRendererTest.php`
  - Passed: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-devicen-transfer-mask-currentbase.php`
  - Passed: no syntax errors.
- `php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`
  - Passed: `json ok`.
- `git diff --check -- lanes/markerpdf`
  - Passed: no whitespace errors.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, Indexed soft-mask transfer review, DeviceN ICCBased stream preview, named resource color-space soft-mask alpha, ColorKey/SMask conflict handling, ImageMask stencil Decode, DCTDecode CMYK/YCCK Decode review, or soft-mask stream-filter decoding. The new behavior is specifically DeviceN tint sample preview plus a soft-mask dictionary `/TR` transfer function on the current accepted base.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF parser, DeviceN/Separation color-space metadata planner, soft-mask dictionary parser, FunctionType 2 transfer sampler, and WordPress image-review smoke path. Full pixel parity remains gated on pypdfium2/PIL or a future native raster backend.
