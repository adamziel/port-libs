# markerPDF DeviceGray SMask Transfer Current-Base

Slice: `image-devicegray-smask-transfer-currentbase`
Session: `port-dev-markerpdf-image46pdf-20260602T1927Z`
Base accepted HEAD: `0962bc173d9405ad2a4150597c334fce11dba6e5`

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/images.py`, renders a PDF page through `page.render(scale=dpi / 72, draw_annots=False).to_pil()` and converts the output to RGB. The native PHP boundary therefore records the image sample, soft-mask, and RGB-preview decisions that PDFium would apply before WordPress media review.
- `pypdfium2` `PdfPage.render()` delegates to PDFium page rendering and chooses RGB/BGR/alpha-capable bitmap formats; this slice keeps that raster work out of PHP and stores deterministic review rows only.
- PDF parser semantics for DeviceGray images: an image `/Decode` array maps raw component samples before color interpretation; a soft-mask transfer function maps the alpha/luminosity input before compositing. A current xref-selected `/SMask` image stream supplies grayscale alpha samples; stale object-map entries must not override the selected SMask object.

## Change

- Added `PdfImageRenderer::deviceGraySamplePreview()` for DeviceGray sample Decode mapping, RGB component expansion, and optional soft-mask `/TR` transfer-function alpha.
- Added `PdfImageRenderer::deviceGrayImageStreamPreviewRows()` for native filter-chain decoding of DeviceGray image streams and current-object grayscale SMask streams into bounded per-pixel preview rows.
- Added `PdfImageDeviceGraySmaskTransferCurrentBaseTest.php` with red-first coverage for transfer-function alpha and current-object SMask stream rows.
- Added `wordpress-pdf-image-devicegray-smask-transfer-currentbase.php` as a WordPress image-block smoke that emits DeviceGray decoded gray, transfer alpha, SMask source object, and no-external-tool flags.

## Evidence

Red-first before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceGraySmaskTransferCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL applies DeviceGray image Decode and soft-mask transfer functions before RGB preview
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::deviceGraySamplePreview()
FAIL decodes current DeviceGray image and soft-mask streams into preview rows
Call to undefined method PortLibs\MarkerPDF\PdfImageRenderer::deviceGrayImageStreamPreviewRows()
1 test files, 0 assertions, 2 failures
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceGraySmaskTransferCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS applies DeviceGray image Decode and soft-mask transfer functions before RGB preview
PASS decodes current DeviceGray image and soft-mask streams into preview rows
1 test files, 44 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-devicegray-smask-transfer-currentbase.php
```

Smoke emitted `source_color_space=DeviceGray`, `decoded_gray=0.74902`, `soft_mask_alpha_before_transfer=0.5`, `soft_mask_alpha=0.3`, `stream_soft_mask_source_object=42`, `stream_soft_mask_uses_current_object_map=true`, `output_color_mode=RGB`, and `executes_python_or_models=false`, `executes_external_pdf_tools=false`, `executes_pypdfium_or_pil=false`.

## Non-Overlap

This does not repeat accepted Indexed soft-mask transfer, DeviceN transfer/tint metadata, inline ImageMask preview rows, ColorKey mask precedence, JPX/JBIG2 review-only image filters, DCT CMYK Decode, named ColorSpace SMask, ICCBased/DeviceN stream preview, or generic soft-mask filter-boundary metadata. The new behavior is specifically DeviceGray base-image sample Decode plus SMask transfer alpha and current-object DeviceGray SMask stream rows.

## Dependency Closure

No new support component is needed. The slice reuses native PDF value parsing, current object-map resolution, image stream filter decoding, packed sample reads, image `/Decode` mapping, and existing FunctionType 2 soft-mask transfer evaluation. Full raster parity remains gated on pypdfium2/PIL/PDFium or a future native raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
