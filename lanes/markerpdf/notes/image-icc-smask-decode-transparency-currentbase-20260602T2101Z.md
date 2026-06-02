# markerPDF image ICC SMask Decode transparency current-base

Slice: `image-icc-smask-decode-transparency-currentbase`

Base accepted HEAD: `dc17119479f92562b7d16aa7377f5088a0295935`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page images through `marker/pdf/images.py::render_image()`: PDFium renders at `dpi / 72` with annotations disabled, then PIL converts the image to RGB. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py>
- Upstream image extraction inserts the rendered/cropped RGB image into page image slots through `marker/images/extract.py::extract_page_images()`. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py>
- The native PHP boundary stays parser-side: ICC profile conversion and full raster compositing are not executed, but image stream filters, `/Decode` ranges, `/SMask` stream filters, and soft-mask alpha values are decoded into deterministic review rows before a future RGB compositor.

## Behavior

Added `PdfImageRenderer::iccBasedSamplePreview()` and `PdfImageRenderer::iccBasedImageStreamPreviewRows()`.

The new preview path:

- accepts only `/ICCBased` image streams;
- decodes native image filter chains such as ASCIIHex plus Flate on the current object body;
- applies explicit image `/Decode` ranges before ICC profile review;
- decodes the current `/SMask` image stream through its own filter chain;
- maps the SMask `/Decode` array into per-pixel alpha;
- records ICC metadata, stream hashes, soft-mask source object, current-object-map use, and RGB output mode without executing pypdfium, PIL, Python models, or external PDF tools.

The WordPress smoke emits an image block with ICCBased color-space metadata and a decoded soft-mask alpha swatch while keeping ICC bytes, SMask bytes, and image bytes out of visible paragraph content.

## Red-first

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes ICCBased image stream samples and SMask Decode alpha before RGB preview (lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php)
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::iccBasedImageStreamPreviewRows()

1 test files, 0 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes ICCBased image stream samples and SMask Decode alpha before RGB preview

1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageDeviceGraySmaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageCalibratedJbig2SoftMaskCurrentBaseTest.php
6 test files, 750 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-icc-smask-decode-transparency-currentbase.php > /tmp/markerpdf-icc-smask-smoke.html
wc -c /tmp/markerpdf-icc-smask-smoke.html
2328 /tmp/markerpdf-icc-smask-smoke.html
rg "markerpdf:pdf-image-icc-smask-decode-transparency-currentbase|data-marker-color-space=\"ICCBased\"|data-marker-soft-mask-source=\"42\"" /tmp/markerpdf-icc-smask-smoke.html
passed
```

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-icc-smask-decode-transparency-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-icc-smask-decode-transparency-currentbase.php
```

```text
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "JSON OK\n";'
JSON OK
```

```text
git diff --check -- lanes/markerpdf
passed
```

## Non-overlap

This does not repeat accepted ICC soft-mask metadata-only planning, Indexed ICCBased JBIG2 palette review, DeviceN ICCBased alternate-colorant preview, DeviceGray stream preview, calibrated stream preview, ColorKey mask precedence, inline image masks, JPX/JBIG2 review-only filters, or xref/parser stream-filter owner boundaries.

The new behavior is specifically direct ICCBased image stream sample-row preview with current image filter decoding, explicit image `/Decode`, decoded grayscale `/SMask` filter bytes, and soft-mask transparency alpha rows before RGB WordPress review.

## Status delta

- `phpPass`: `811 -> 812`
- `wordpressScenarios`: `811 -> 812`
- New focused assertions: `45`
- Expected dashboard movement: one new markerPDF behavior PASS line plus one WordPress smoke path.

## Dependency closure

No new support component is needed. This slice reuses the native PDF value parser, ICCBased color-space metadata, stream filter decoder, DecodeParms predictor handling, packed image-sample reader, soft-mask Decode opacity mapping, and WordPress smoke path. Full upstream raster parity remains dependency-gated by pypdfium2/PDFium, PIL, pdftext, Surya/Torch, tabled-pdf, Texify, model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers.
