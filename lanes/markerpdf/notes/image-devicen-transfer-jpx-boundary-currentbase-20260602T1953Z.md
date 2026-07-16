# markerPDF DeviceN Transfer JPX Boundary Current Base

Session: `port-dev-markerpdf-image48-20260602T1953Z`
Micro-slice: `image-devicen-transfer-jpx-boundary-currentbase`
Base accepted HEAD: `ca550807cded80a5a0bf98599fdd8ae923c222c8`

## Source Truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/images.py`: `render_image()` renders with pypdfium at `dpi / 72`, disables annotation drawing, converts the rendered page to PIL, and converts to RGB; `render_bbox_image()` crops the rendered RGB image.
- Source link: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- PDF image boundary: `/JPXDecode` JPEG 2000 image bytes, DeviceN tint transforms, transparency soft-mask dictionaries, and `/SMaskInData` alpha remain raster/PDF-engine responsibilities. The PHP port records parser-side review metadata before the RGB handoff without executing JPEG 2000 raster decoding.

## Behavior

`PdfImageRenderer::alternateColorantStreamPreviewRows()` now handles DeviceN/Separation image streams whose primary image filter is preview-only, such as `/JPXDecode`, the same way the Indexed and calibrated stream paths already did:

- returns `review_only_image_stream=true` instead of throwing;
- preserves image filter metadata, DeviceN colorants, alternate color space, tint-transform source, image `/Decode`, and output RGB intent;
- preserves external soft-mask transparency group and supported `/TR` FunctionType 2 metadata without inventing native alpha samples when the primary JPX raster samples are unavailable;
- keeps nonzero JPX `/SMaskInData` authoritative over external `/SMask` transfer dictionaries.

The WordPress smoke emits a Gutenberg image block carrying review metadata and confirms `executes_python_or_models=false`, `executes_pypdfium_or_pil=false`, `executes_external_pdf_tools=false`, and `executes_jpx_raster_decode=false`.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps DeviceN JPX image streams review-only while preserving transfer soft-mask metadata
Alternate colorant image stream filters must be natively decoded before RGB preview.
PASS keeps JPX SMaskInData authoritative over external DeviceN transfer masks
1 test files, 10 assertions, 1 failures
```

## Verification

Focused tests already run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php
1 test files, 42 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 516 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php
2 test files, 97 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php
3 test files, 613 assertions, 0 failures
```

WordPress smoke already run:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-devicen-transfer-jpx-boundary-currentbase.php
```

It emitted `source_color_space=DeviceN`, `preview_only_filters=["JPXDecode"]`, `review_only_image_stream=true`, `native_raster_decode=false`, `decoded_image_bytes=null`, `soft_mask_subtype=Luminosity`, `soft_mask_transfer_applied_before_rgb=true`, `stream_notes=["alternate_colorant_image_stream_preview_only_before_rgb_conversion","soft_mask_transfer_function_reviewed_without_raster_samples"]`, and all execution flags false.

Final checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-devicen-transfer-jpx-boundary-currentbase.php
No syntax errors detected.

php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
json ok

git diff --check -- lanes/markerpdf
passed
```

## Status Delta

- Behavior tests: `745 -> 747` pass / `0` fail.
- Mapped semantics: `532 -> 533 / 78`.
- Added WordPress smoke: `examples/wordpress-pdf-image-devicen-transfer-jpx-boundary-currentbase.php`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DeviceN soft-mask transfer sample preview, DeviceN ICCBased decoded stream rows, Indexed JPX review-only soft-mask streams, JPX `/SMaskInData` ColorKey suppression, inline JPX SMaskInData review, DeviceGray soft-mask transfer rows, DCTDecode CMYK/YCCK Decode review, or generic JPX/JBIG2 text-exclusion boundaries. The new behavior is specifically alternate-colorant DeviceN/Separation stream review when the primary image stream is JPX preview-only, including soft-mask transfer dictionary metadata and embedded JPX alpha precedence.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary/value parser, image filter metadata, DeviceN/Separation color-space planner, soft-mask transparency-group parser, transfer-function metadata sampler, and WordPress smoke path. Full live pixel parity remains gated on pypdfium2/PIL/PDFium or a future native JPX/raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, OCR, JPEG 2000 decoders, or external PDF tools.
