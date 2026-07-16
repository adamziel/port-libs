# markerPDF DCTDecode CMYK Decode Review

Session: `port-dev-markerpdf-image18pdf-20260602T1408Z`
Micro-slice: `image-jpeg-cmyk-decode-review-currentbase-20260602T1408Z`
Base accepted HEAD: `e92df18f9a63d857eb719b1ac9d04dd454003a3a`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders with `scale=dpi / 72`, disables annotations, converts the page/crop to RGB, and `marker/images/extract.py::extract_page_images()` inserts the image Markdown span.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://developer.adobe.com/document-services/docs/assets/35e4369068f86065372c18787171a17e/PDF_ISO_32000-1.pdf - PDF ISO 32000 DCTDecode `ColorTransform` behavior: Adobe APP14 transform markers override `/DecodeParms /ColorTransform`, and four-component JPEGs transform YUVK/YCCK to CMYK when enabled.

This native PHP slice keeps the same RGB-preview boundary without pypdfium/PIL. It adds the parser-side `/Decode` review step for DCTDecode CMYK/YCCK image samples before the preview helper maps CMYK into RGB.

## Native Behavior Added

`PdfImageRenderer::dctDecodeImageColorPlan()` now exposes:

- direct or indirect image `/Decode` arrays for DCTDecode JPEG images;
- `image_decode_applied_before_rgb` and `image_decode_component_mismatch` flags;
- `image_decode_applied_before_rgb_conversion`, `image_decode_inverts_components_before_rgb`, and mismatch review notes.

`PdfImageRenderer::dctDecodeSampleToRgb()` now applies valid DCT image `/Decode` ranges after JPEG/Adobe color transform handling and before CMYK-to-RGB preview conversion. Invalid component counts remain review metadata and are not applied.

## Evidence

Red-first focused failure after adding the test, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 150 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 164 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-cmyk-decode-review.php
```

The smoke emits `image_decode_applied_before_rgb=true`, `image_decode_inverted_components=[0]`, `adobe_app14_transform=2`, `effective_color_transform=2`, `rgb_preview_sample={"red":1,"green":0,"blue":0}`, `output_color_mode=RGB`, and all execution flags false for Python/models, pypdfium/PIL, and external PDF tools.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-cmyk-decode-review.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCTDecode CMYK Adobe APP14 transform planning, generic base-image `/Decode` sample mapping, `/ImageMask` stencil decode, soft-mask `/Decode`, ICCBased soft-mask metadata, Indexed ICC/JBIG2 palette review, Separation/DeviceN alternate-color metadata, soft-mask stream-filter decoding, or image-filter text exclusion. It only covers DCTDecode CMYK/YCCK image `/Decode` review and sample mapping before RGB preview conversion.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF dictionary/value parsing and `PdfImageRenderer` sample helpers. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, pypdfium, or PIL.
