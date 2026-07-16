# markerPDF Separation/DeviceN CCITT Preview Boundary

Session: `port-dev-markerpdf-image3pdf-20260602T111809Z`
Micro-slice: `image-colorspace-filter-preview-boundary-currentbase-20260602T111809Z`
Base accepted HEAD: `c891c4a56626f60c5cf22b9960401b6cb3d2d5d7`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page crops through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders with `scale=dpi / 72`, disables annotations, converts the result to RGB, and `marker/images/extract.py::extract_page_images()` inserts the image Markdown span.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

This native PHP slice keeps that RGB-preview boundary without pypdfium/PIL. It records parser-side decisions that must happen before any future raster backend converts pixels: Separation/DeviceN alternate color spaces, tint-transform function references, Decode arrays, and CCITTFaxDecode preview-filter DecodeParms.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits:

- `uses_alternate_color_space` and `alternate_color_space` metadata for `/Separation` and `/DeviceN` image color spaces.
- Decoded colorant names, including PDF name escapes such as `#20` and `#48`.
- Alternate color-space component counts, ICC profile propagation, tint-transform object/function metadata, and DeviceN attributes presence.
- `image_filter_details` metadata for image filters, with `/CCITTFaxDecode` and `/CCF` marked preview-only and their `/DecodeParms` fields preserved.
- JPX, JBIG2, and CCITT review-only filter notes without claiming native raster decoding.

## Evidence

Red-first focused failure after adding the test, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 123 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 141 assertions, 0 failures
```

Adjacent media/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/ImageExtractorTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
4 test files, 263 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-separation-devicen-ccitt-preview-boundary.php
```

The smoke emits `source_color_space=Separation`, `colorant_names=["PANTONE 485 C"]`, `alternate_color_space=ICCBased`, `tint_transform_object=40`, `image_filters=["CCITTFaxDecode","ASCIIHexDecode"]`, `ccitt_decode_parms.K=-1`, `devicen_colorants=["Cyan","Spot Varnish"]`, `devicen_preview_filter=["JPXDecode"]`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Status Delta

- Focused assertions: `121 -> 141` in `PdfImageRendererTest.php`.
- Behavior tests: `481 -> 482`.
- Mapped semantics: `330 -> 331 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, base image `/Decode` mapping, `/ImageMask` stencil decode, soft-mask `/Decode`, Indexed ICCBased palette lookup, JBIG2 globals metadata, DCTDecode CMYK Adobe-transform planning, CCITT text-extraction exclusion, or the previous Indexed ICCBased JBIG2 soft-mask preview boundary. The new behavior is specifically alternate-color-space image preview metadata plus CCITT preview-filter parameter preservation.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser and `PdfImageRenderer` review planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, or raster rendering.
