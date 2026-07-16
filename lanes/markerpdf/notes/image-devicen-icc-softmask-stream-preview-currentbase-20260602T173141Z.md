# markerPDF DeviceN ICC Soft-Mask Stream Preview

Session: `port-dev-markerpdf-image36pdf-20260602T173141Z`
Micro-slice: `image-devicen-icc-softmask-preview-currentbase-20260602T173141Z`
Base accepted HEAD: `f6a226052136abadc56f7b8d8b89c4b84d502d1b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page/crop images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`, then `marker/images/extract.py::extract_page_images()` emits Markdown image spans. The upstream renderer delegates real PDF color conversion, transparency, and image filtering to PDFium/PIL and returns RGB preview output.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf

This native PHP slice preserves that RGB-preview boundary without pypdfium/PIL. The parser-side behavior covered here is the current-object image stream path for `/DeviceN` colorants with an indirect ICCBased alternate color space and an image XObject `/SMask`: both the image bytes and SMask bytes must decode through the current object map before per-pixel tint/alpha review rows are handed to WordPress.

## Native Behavior Added

`PdfImageRenderer::alternateColorantStreamPreviewRows()` now:

- decodes natively supported image stream filters before preview row extraction;
- fails closed for preview-only filters such as JPX/JBIG2/CCITT/DCT or malformed streams;
- unpacks PDF image component samples from 1/2/4/8/16-bit style packed rows;
- applies the existing `/Decode` tint mapping through `alternateColorantSamplePreview()`;
- decodes the referenced `/SMask` stream through the current object map, validates dimensions, and applies grayscale `/Decode` alpha per pixel;
- preserves ICCBased alternate profile metadata, tint-transform object/function metadata, and RGB output intent without executing tint-transform PostScript, Python models, PDFium, PIL, or external PDF tools.

`examples/wordpress-pdf-devicen-icc-softmask-stream-preview-currentbase.php` emits a WordPress image-block review comment for a 3-pixel DeviceN stream whose image bytes and soft-mask bytes are both ASCIIHex+Flate decoded before preview rows are emitted.

## Evidence

Red-first focused failure after adding the test before the source helper:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
FAIL maps decoded DeviceN ICCBased image and soft-mask stream rows before RGB preview
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::alternateColorantStreamPreviewRows()
1 test files, 342 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 379 assertions, 0 failures
```

Adjacent image/media gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/ImageExtractorTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
4 test files, 501 assertions, 0 failures
```

Required checks run for this handoff:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-devicen-icc-softmask-stream-preview-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-devicen-icc-softmask-stream-preview-currentbase.php
git diff --check -- lanes/markerpdf
```

Status delta: behavior tests `604 -> 605`; mapped image semantics `438 -> 439 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing alone, Separation/DeviceN alternate-color metadata, DeviceN single-sample tint preview, soft-mask presence and `/Matte` metadata, soft-mask `/Decode` opacity alone, soft-mask stream-filter metadata alone, Indexed ICCBased/JBIG2 palette preview, Indexed default Decode/hival clipping, DCTDecode CMYK/YCCK Decode review, CCITT/JPX/JBIG2 preview-only boundaries, inline image review, or image-filter text-exclusion boundaries.

The new behavior is specifically the combined current stream-row path: DeviceN image stream filters decode to component samples, the ICCBased alternate remains review metadata, and a separately filtered current `/SMask` stream supplies per-pixel alpha before WordPress RGB image review.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, stream filter-chain decoder, DecodeParms predictor handling, DeviceN/ICCBased color-space planner, soft-mask decoder, and WordPress smoke path. Full live raster parity remains dependency-gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pypdfium, PIL, Poppler, Ghostscript, OCR, or other external PDF tools.
