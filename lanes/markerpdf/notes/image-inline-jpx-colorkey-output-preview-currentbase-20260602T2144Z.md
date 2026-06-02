# markerPDF Inline JPX ColorKey Output Preview Current Base

Session: `port-dev-markerpdf-image65-20260602T214409Z`
Micro-slice: `image-inline-jpx-colorkey-output-preview-currentbase`
Base accepted HEAD: `2f3fc4b0f9b0d3173d4fbbfd044e1af271ae2d5b`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page and crop images through PDFium and converts the PIL result to RGB before inserting image Markdown:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

The native PHP port still does not execute PDFium, PIL, Python models, or a JPX raster backend. This slice therefore keeps inline JPX bytes review-only and maps explicitly supplied decoded JPEG2000 sample rows through PDF ColorKey `/Mask` and `/Decode` metadata before WordPress RGB output-preview review.

## Behavior

`PdfImageRenderer::inlineJpxColorKeyOutputPreviewRows()` now:

- expands inline-image abbreviations and preserves the canonical inline dictionary;
- keeps `JPXDecode` payload bytes review-only with `native_jpx_raster_decode=false`;
- accepts bounded supplied decoded JPX samples as fixture/backend output rows;
- applies ColorKey transparency against raw samples before `/Decode`;
- emits RGB output-preview rows with alpha while keeping inline payload text out of WordPress paragraphs.

`examples/wordpress-pdf-inline-jpx-colorkey-output-preview-currentbase.php` emits an image block with `data-marker-preview-only="true"`, `data-marker-native-jpx-raster-decode="false"`, `data-marker-color-key-mask="true"`, transparent/opaque alpha metadata, and visible paragraph text excluding inline JPX payload operators.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php
FAIL maps inline JPX ColorKey supplied output samples without claiming native raster decode
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::inlineJpxColorKeyOutputPreviewRows()
PASS keeps inline JPX ColorKey payload bytes out of WordPress text import
1 test files, 5 assertions, 1 failures
```

Green focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageJpeg2000MaskDecodePreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
6 test files, 759 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-jpx-colorkey-output-preview-currentbase.php
```

The smoke emitted `visible_text_imported=true`, `excluded_inline_jpx_payload_text=true`, `review_only_image_stream=true`, `native_jpx_raster_decode=false`, `uses_supplied_jpx_samples=true`, `transparent_output_rgba={"red":0,"green":127,"blue":240,"alpha":0}`, `opaque_output_rgba={"red":40,"green":191,"blue":180,"alpha":1}`, and all Python/model/PDFium/PIL/external-tool execution flags false.

## Status Delta

- Behavior tests: `864 -> 866` pass / `0` fail.
- Mapped semantics: `610 -> 611 / 78`.
- Focused assertion growth: new direct test file adds `41` assertions; adjacent focused image gate passed `759` assertions.
- WordPress smoke: added `wordpress-pdf-inline-jpx-colorkey-output-preview-currentbase.php`.

## Non-overlap

This does not repeat accepted JPX/JBIG2 filter exclusion, JPX `/SMaskInData` ColorKey suppression, invalid JPX `/SMaskInData` fallback, inline JPX external SMask Decode metadata, JPEG2000 ImageMask supplied-sample preview rows, inline ImageMask preview rows, decoded inline Indexed palette/alpha rows, XObject Indexed ColorKey transfer, DeviceN JPX transfer-mask review, PDF/A JPX OutputIntent context, or generic inline payload exclusion. The bounded new behavior is specifically inline DeviceRGB JPX `/Mask` ColorKey output-preview rows from supplied decoded samples while the JPX payload remains review-only.

## Dependency Closure

No new support component is needed. The slice reuses the native inline-image dictionary expander, PDF value parser, image filter review boundary, ColorKey `/Mask` planner, `/Decode` mapper, text extractor inline image exclusion, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL/PDFium or a future native JPX raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, OCR, or external PDF tools.
