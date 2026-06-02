# markerPDF Named Image ColorSpace Soft-Mask Current Base

Session: `port-dev-markerpdf-image41pdf-20260602T1829Z`
Micro-slice: `image-colorspace-smask-currentbase`
Base accepted HEAD: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF image rendering through `marker/pdf/images.py`: page and bbox images render through PDFium with annotations disabled, then PIL converts the result to RGB. The native PHP lane cannot execute PDFium/PIL here, so image color-space and alpha operands are represented as review metadata before any future RGB raster backend.

Source inspected: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Behavior

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now resolves a named image `/ColorSpace` through the current object-map resource dictionary before source color-space planning. A current page `/Resources << /ColorSpace << /CSspot [...] >> >>` entry now preserves:

- the resolved resource name, value, and source path;
- Separation/DeviceN alternate color-space metadata and tint-transform object review;
- current SMask stream filter decoding and `/Decode` alpha handling before RGB preview metadata;
- a fallback path where unresolved non-standard names remain unresolved instead of guessing a component count.

The WordPress smoke `examples/wordpress-pdf-image-named-colorspace-smask-currentbase.php` emits a Gutenberg image block with `source_color_space=Separation`, `color_space_resource_name=CSspot`, `color_space_resource_source=Resources.ColorSpace`, `stale_resource_map_excluded=true`, `soft_mask_decoded_sample_bytes=[32,128,224]`, and execution flags false.

## Verification

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` passed.
- `php -l lanes/markerpdf/tests/PdfImageRendererTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-named-colorspace-smask-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php` passed: `1 test files, 445 assertions, 0 failures` with 27 focused PASS rows.
- `php lanes/markerpdf/examples/wordpress-pdf-image-named-colorspace-smask-currentbase.php` passed and emitted `color_space_resolved_from_resources=true`, `alternate_color_space=DeviceCMYK`, `soft_mask_decoded_with_current_filters=true`, `soft_mask_alpha=0.498039`, and all Python/PDFium/PIL/external-tool execution flags false.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP PASS rows: `640 -> 641`.
- Mapped markerPDF behavior count: `467 -> 468 / 78`.
- Added WordPress smoke: `examples/wordpress-pdf-image-named-colorspace-smask-currentbase.php`.

## Non-Overlap

This does not repeat accepted direct ICCBased/DeviceN color-space preview, Indexed/Separation palette tint preview, soft-mask `/Decode`, soft-mask stream-filter boundary, soft-mask transfer functions, ColorKey-mask conflict handling, inline JPX soft-mask review, DCT CMYK `/Decode`, JPX/JBIG2 filter boundaries, or parser stream-owner work. The new behavior is specifically named image `/ColorSpace` resource resolution from the current resource dictionary before SMask alpha review.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary/value parser, object-map resolver, supported ASCIIHex/Flate stream decoders, Separation/DeviceN colorant metadata, and soft-mask alpha sampler. Full upstream raster parity remains gated on pypdfium2/PDFium and PIL, plus the broader markerPDF Python/model stack.

## Next

Continue with non-overlapping markerPDF image/parser/page/action/table/security current-base gaps that add focused PHP behavior coverage without executing Python, models, pypdfium, PIL, or external PDF tools.
