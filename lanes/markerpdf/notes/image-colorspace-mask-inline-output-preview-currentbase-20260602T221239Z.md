# markerPDF Image ColorSpace Mask Inline Output Preview Current Base

Session: `port-dev-markerpdf-image72-20260602T221239Z`
Micro-slice: `image-colorspace-mask-inline-output-preview-currentbase`
Base accepted HEAD: `36d3abb94323edf47dc54936168141773ec380c2`

## Source Truth

- Upstream markerPDF `marker/pdf/images.py` renders PDF images through PDFium and converts the rendered bitmap to RGB for output.
- Upstream markerPDF `marker/images/extract.py` inserts image blocks into Markdown while image bytes stay out of visible text.
- Source references:
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py`

## Behavior

Added a native current-base inline image output-preview path in `PdfImageRenderer::inlineImageColorSpaceMaskOutputPreviewRows()`.

The bounded PHP implementation expands inline image dictionaries, decodes native inline samples when the supported filter chain permits it, accepts supplied decoded samples for preview-only JPX streams, and maps samples through ColorSpace, Decode, ColorKey `/Mask`, and current-object `/SMask` alpha into RGB/RGBA review rows. If a soft mask is present, soft-mask alpha wins and color-key transparency is recorded as suppressed. Raw inline payload bytes remain excluded from WordPress-visible text.

## Evidence

- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
  Result: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php`
  Result: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-colorspace-mask-inline-output-preview-currentbase.php`
  Result: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php`
  Result: `1 test files, 67 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceGraySmaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedColorKeyTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  Result: `9 test files, 881 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-image-colorspace-mask-inline-output-preview-currentbase.php`
  Result: WordPress smoke emitted indexed RGBA alpha, review-only JPX supplied-sample RGBA, payload exclusion, and execution flags false.

Status delta: markerPDF focused behavior tests move `895 -> 897` pass / `0` fail; mapped semantics move `631 -> 632 / 78`.

## Non-overlap

This does not repeat the accepted inline JPX ColorKey output preview, inline JPX soft-mask review, inline Indexed palette alpha rows, inline ImageMask preview rows, generic image rendering color-space/soft-mask transfer bundle, or JPX/JBIG2 filter exclusion slices. The new behavior is specifically the inline image output-row composition surface that combines current soft-mask alpha, ColorKey suppression, Decode, Indexed/native samples, and supplied preview-only JPX samples.

## Dependency Closure

No new support component is needed. This slice reuses markerPDF lane-local inline dictionary expansion, stream filter decoding, sample unpacking, ColorSpace/Decode planners, ColorKey review metadata, soft-mask decoding, and text extraction inline-payload exclusion. Full raster parity remains gated on a future native raster backend or Python/PDFium/PIL execution, which remains out of scope for this isolated slice.
