# markerPDF JPEG2000 ImageMask Decode Preview

Session: `port-dev-markerpdf-image52-20260602T2035Z`
Micro-slice: `image-jpeg2000-mask-decode-preview-currentbase`
Base accepted HEAD: `d1072c4d57f8bf8b55795755ca4bcc26ff531e74`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page/crop images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders at `dpi / 72`, annotations are disabled, and PIL converts the result to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- PDF image dictionary behavior: JPXDecode remains a JPEG2000 raster boundary; `/Decode` is ignored for ordinary JPX images but applies to `/ImageMask true`, whose JPEG2000 data supplies a single one-bit channel.

## Native Behavior Added

`PdfImageRenderer::jpeg2000ImageMaskPreviewRows()` now exposes a bounded parser-side review boundary for JPEG2000 `/ImageMask true` streams:

- raw `/JPXDecode` image bytes remain review-only and are not decoded by PHP;
- supplied decoded one-bit JPEG2000 mask samples are unpacked into preview pixels;
- the mask `/Decode` array is applied to produce opacity rows before RGB compositing;
- incomplete supplied mask sample data is reported without reading past available bits;
- non-JPX, non-mask, invalid-dimension, invalid Decode, and non-one-bit mask inputs fail closed.

`examples/wordpress-pdf-jpeg2000-mask-decode-preview-currentbase.php` models a WordPress import where the JPX mask payload contains text-looking PDF operators. The visible paragraph excludes the payload text while the image block carries mask opacity review metadata.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageJpeg2000MaskDecodePreviewCurrentBaseTest.php
1 test files, 37 assertions, 0 failures
```

Focused adjacent image/JPEG2000 gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageJpeg2000MaskDecodePreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
5 test files, 714 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-jpeg2000-mask-decode-preview-currentbase.php
```

The smoke emitted `visible_text_imported=true`, `excluded_jpx_mask_payload_text=true`, `image_filter=["JPXDecode"]`, `review_only_image_stream=true`, `native_jpeg2000_decode=false`, `uses_supplied_jpeg2000_mask_samples=true`, `image_mask_decode="explicit"`, `image_mask_decode_inverted=true`, and `mask_opacity_preview=[1,0,1,0]`.

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageJpeg2000MaskDecodePreviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-jpeg2000-mask-decode-preview-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
```

All passed.

`git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests: `787 -> 790`.
- Focused new assertions: `+37`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted JPX `/SMaskInData` review, ColorKey suppression, external `/SMask` Decode/filter decoding, inline JPX soft-mask dictionary review, inline ImageMask payload decoding, Indexed/JBIG2/ICCBased soft-mask review, DCTDecode CMYK Decode review, or generic image-filter text-exclusion boundaries.

The bounded behavior is only JPEG2000 `/ImageMask true` Decode opacity preview from supplied decoded one-bit JPX mask samples while raw JPX raster bytes stay review-only.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser, image filter boundary metadata, packed sample reader, and ImageMask `/Decode` opacity mapping. Full live JPEG2000 raster parity remains gated on pypdfium2/PIL/PDFium or a future native JPEG2000 decoder; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
