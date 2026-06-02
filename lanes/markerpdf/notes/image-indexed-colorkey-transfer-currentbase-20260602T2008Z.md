# markerPDF Indexed ColorKey Transfer Current Base

Session: `port-dev-markerpdf-image49-20260602T2008Z`
Micro-slice: `image-indexed-colorkey-transfer-currentbase`
Base accepted HEAD: `9efdfcaaff05b4be1ca34b399840525efdf84f93`

## Source Truth

Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its image path renders page/crop images through PDFium and converts the result to RGB before image Markdown insertion:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

The native PHP boundary stays parser-side and review-only. For PDF color-key masks, `/Mask [min max ...]` compares raw image samples before image `/Decode`; for `/Indexed` images the raw sample is a single palette index, then `/Decode` maps that index and the palette lookup transfers it into base color components before the RGB preview handoff.

## Native Behavior

`PdfImageRenderer::indexedColorKeyMaskSamplePreview()` now applies an Indexed image ColorKey mask to the raw palette-index sample, then carries the Decode-adjusted palette index and base color components for WordPress image review.

`PdfImageRenderer::indexedImageStreamPreviewRows()` now adds ColorKey alpha fields only for Indexed streams with an active ColorKey mask:

- `matches_color_key`
- `color_key_alpha`
- `color_key_mask_ranges`
- `decode_applied_after_color_key`
- `palette_transfer_applied_after_color_key`

The stream preview also exposes `alpha_output_mode`, matching the existing image plan.

## WordPress Smoke

`examples/wordpress-pdf-image-indexed-colorkey-transfer-currentbase.php` models an Indexed DeviceRGB palette with `/Decode [3 0]` and `/Mask [1 1]`. Raw index `1` is transparent even though Decode maps it to palette index `2`; raw index `3` remains opaque and transfers to palette index `0`. The smoke emits a Gutenberg image block with `data-marker-mask-compares="raw-index-before-decode"` and `data-marker-palette-transfer-after-mask="true"`, with all Python/model/PDFium/PIL/external-tool execution flags false.

## Verification

Red-first focused failure before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php
1 test files, 4 assertions, 2 failures
```

Focused passing tests after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php
1 test files, 46 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImage*.php
8 test files, 810 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-indexed-colorkey-transfer-currentbase.php
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-indexed-colorkey-transfer-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-indexed-colorkey-transfer-currentbase.php

jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
passed

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

Expected lane movement:

- PHP behavior tests: `758 -> 760`
- mapped markerPDF/PDF image semantics: `540 -> 541 / 78`

## Non-Overlap

This does not repeat accepted DeviceRGB ColorKey-before-Decode masks, ColorKey suppression by soft masks, JPX `SMaskInData` precedence, Indexed default Decode and soft-mask alpha, Indexed Separation/DeviceN soft-mask transfer, inline ImageMask previews, ICC/JBIG2/JPX image-filter boundaries, or DCT CMYK Decode planning. The new behavior is specifically an Indexed image ColorKey mask whose raw palette-index comparison precedes Decode and palette transfer.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary/value parser, stream-filter decoder, packed sample reader, Indexed palette planner, and `PdfImageRenderer` review surface. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, pypdfium, PIL, PDF actions, decryption, or signature validation.
