# markerPDF Image Rendering Color-Space Soft-Mask Transfer Bundle

Session: `port-dev-markerpdf-image69-20260602T215653Z`
Micro-slice: `image-rendering-colorspace-softmask-transfer-bundle-currentbase`
Base accepted HEAD: `0059bb644ec3506849ecf93d4f87651501a9af5b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF pages and image crops through PDFium, converts the rendered page/crop to RGB, and inserts image Markdown spans after image-region detection. The native PHP port keeps the same RGB handoff boundary without executing Python, pypdfium2, PIL, models, or external PDF tools.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

PDF source behavior covered here: image XObject rendering depends on the current page/resource color-space dictionary, the current `/SMask` object, and any soft-mask transfer function before RGB compositing. JPX/JBIG2/CCITT/DCT raster streams remain preview-only in PHP, while Flate/ASCIIHex/ASCII85/RunLength sample streams can be decoded into bounded review rows.

## Native Behavior Added

- Added `PdfImageRenderer::imageRenderingColorSpaceSoftMaskTransferBundle()` as a generic image-rendering dispatcher over the existing Indexed, Separation/DeviceN, calibrated, ICCBased, and DeviceGray stream preview helpers.
- The returned `render_bundle` summary records the selected native preview path, named ColorSpace resource resolution, image stream decode/review-only state, current soft-mask source object, current-object soft-mask stream decode state, soft-mask transfer-function presence/application, RGB/alpha output modes, and non-execution flags.
- Added focused coverage for:
  - a named resource `/CSspot` DeviceN JPX image whose raster bytes remain review-only while a current soft-mask transparency dictionary and Type 2 `/TR` transfer function are preserved before RGB review;
  - a named resource `/CSgold` Separation Flate image whose current-object grayscale `/SMask` stream is decoded into alpha rows before WordPress media review.
- Added `examples/wordpress-pdf-image-rendering-colorspace-softmask-transfer-bundle-currentbase.php` to emit a WordPress image block and review metadata without exposing image or soft-mask payload bytes.

## Evidence

Focused bundle test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bundles named DeviceN JPX color space and soft-mask transfer review before RGB preview
PASS bundles current named Separation stream and decoded soft-mask rows before RGB preview

1 test files, 47 assertions, 0 failures
```

Related image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceGraySmaskTransferCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
11 PASS lines
6 test files, 288 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-rendering-colorspace-softmask-transfer-bundle-currentbase.php
```

Smoke emitted `transfer_bundle.selected_preview=alternate_colorant`, `transfer_bundle.image_stream_review_only=true`, `transfer_bundle.soft_mask_transfer_applied_before_rgb=true`, `stream_bundle.color_space_resource_name=CSgold`, `stream_bundle.soft_mask_source_object=82`, `stream_bundle.soft_mask_stream_decoded=true`, `image_payload_bytes_exposed=false`, `soft_mask_payload_bytes_exposed=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-rendering-colorspace-softmask-transfer-bundle-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
git diff --check -- lanes/markerpdf
```

All passed locally.

Status delta: behavior tests `880 -> 882` pass / `0` fail. Mapped semantics `621 -> 622 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased stream rows, DeviceGray transfer rows, DeviceN/Separation SMask Decode rows, Indexed DeviceN transfer rows, DeviceN JPX transfer review-only rows, named ColorSpace SMask sample-only planning, ColorKey masks, inline image masks, JPX SMaskInData/PDF-A rows, DCT CMYK Decode review, or generic SMask filter-boundary metadata.

The bounded behavior here is the unified image-rendering bundle API and review summary over those already separated native paths, proving current ColorSpace resources, current SMask objects, and transfer-function boundaries stay connected for WordPress image import.

## Dependency Closure

No new support component is needed. This reuses native PDF dictionary/value parsing, current object/resource resolution, image filter decoding, packed sample reads, Decode mapping, alternate color-space planning, and FunctionType 2 soft-mask transfer evaluation. Full raster parity remains dependency-gated on pypdfium2/PIL/PDFium or a future native raster backend.
