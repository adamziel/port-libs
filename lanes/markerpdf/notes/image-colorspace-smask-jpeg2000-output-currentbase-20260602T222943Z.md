# markerPDF Image ColorSpace SMask JPEG2000 Output Current Base

Session: `port-dev-markerpdf-image75-20260602T222943Z`
Micro-slice: `image-colorspace-smask-jpeg2000-output-currentbase`
Base accepted HEAD: `bb708b034859e01609243fd0084dfe679ed88069`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders pages through PDFium and converts PIL images to RGB in `marker/pdf/images.py`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

The native PHP lane still does not execute Python, pypdfium, PIL, model code, or a native JPEG2000 raster backend. This slice preserves the PDF-side boundary before that RGB output: supplied decoded JPX samples are treated as bounded decoder output, then image `/Decode`, current grayscale `/SMask`, and PDF/A OutputIntent context are applied into reviewable RGB/RGBA output rows.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorSpaceSmaskJpeg2000OutputCurrentBaseTest.php`

Before implementation this failed with:

```text
FAIL maps supplied JPEG2000 color samples and current SMask alpha into RGB output rows
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::jpeg2000ColorSpaceSoftMaskOutputPreviewRows()
FAIL reports incomplete supplied JPEG2000 samples and rejects unsupported output boundaries
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::jpeg2000ColorSpaceSoftMaskOutputPreviewRows()
1 test files, 0 assertions, 2 failures
```

## Behavior

`PdfImageRenderer::jpeg2000ColorSpaceSoftMaskOutputPreviewRows()` now:

- requires a `JPXDecode` image stream and keeps `native_jpx_raster_decode=false`;
- accepts bounded supplied decoded JPX sample rows for `DeviceRGB` and `DeviceGray`;
- resolves current named color-space resources before applying image `/Decode`;
- decodes the current external grayscale `/SMask` stream through native filters and applies soft-mask `/Decode` before RGBA output;
- preserves PDF/A OutputIntent color context without exposing ICC/profile payload bytes;
- reports incomplete supplied JPX samples without treating raw JPEG2000 bytes as decoded pixels;
- rejects non-JPX, `/ImageMask`, unsupported color transform, missing SMask, non-grayscale SMask, and mismatched component boundaries.

The WordPress smoke `examples/wordpress-pdf-image-colorspace-smask-jpeg2000-output-currentbase.php` emits a review-only image block with `data-marker-native-jpx-raster-decode="false"`, current color-space resource metadata, current SMask source, and JSON output RGBA rows while excluding hidden JPX payload text from visible import text.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorSpaceSmaskJpeg2000OutputCurrentBaseTest.php` -> `1 test files, 61 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorSpaceSmaskJpeg2000OutputCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageJpxSmaskColorSpacePdfaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageJpeg2000MaskDecodePreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php` -> `7 test files, 775 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-colorspace-smask-jpeg2000-output-currentbase.php` -> emitted JPX/SMask RGB output rows with `visible_text_imported=true`, `excluded_jpeg2000_payload_text=true`, `native_jpx_raster_decode=false`, and all execution flags false.

Final lint, manifest JSON validation, and `git diff --check -- lanes/markerpdf` were run after the lane artifacts were updated.

## Status Delta

- Behavior tests: `915 -> 917` pass / `0` fail.
- Mapped semantics: `644 -> 645 / 78`.
- WordPress smoke: added `wordpress-pdf-image-colorspace-smask-jpeg2000-output-currentbase.php`.

## Non-Overlap

This does not repeat accepted JPEG2000 `/ImageMask` supplied-sample preview rows, inline JPX ColorKey output rows, JPX SMaskInData PDF/A review metadata, DeviceN/Separation JPX transfer review, generic named ColorSpace/SMask bundle metadata, ICCBased soft-mask stream decoding, Indexed ColorKey transfer, or generic image-filter text exclusion. The bounded behavior is specifically XObject JPX color sample output rows composed with current external SMask alpha and PDF/A color context while JPX bytes remain review-only.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary/value parser, current resource color-space resolver, image `/Decode` planner, current SMask stream-filter decoder, PDF/A OutputIntent review helper, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PIL/PDFium or a future native JPEG2000 raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, OCR, or external PDF tools.
