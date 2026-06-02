# markerPDF Calibrated JBIG2 Soft-Mask Current Base

Session: `port-dev-markerpdf-image45pdf-20260602T1917Z`
Micro-slice: `image-calibrated-jbig2-softmask-currentbase`
Base accepted HEAD: `4dc1f21b98948ff243f10a6054e126d012098006`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes image preview through `marker/pdf/images.py`: PDFium renders at `dpi / 72`, disables annotations, converts the page image to RGB, and `render_bbox_image()` crops that RGB output. `marker/images/extract.py` inserts the resulting image span into the page model.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

The native PHP lane does not execute PDFium, PIL, Python models, or external raster tools. This slice preserves the parser-side state needed before a future raster backend can match that RGB boundary: calibrated CIE image dictionaries, JBIG2 preview-only filter handling with `/JBIG2Globals` metadata, and a separately decoded current-object soft mask.

## Implementation

`PdfImageRenderer::calibratedImageStreamPreviewRows()` now maps CalGray, CalRGB, and Lab image streams through the existing calibrated color-space plan:

- native image filters produce bounded sample rows with decoded calibrated components and matching soft-mask alpha;
- JBIG2/JPX/CCITT/DCT image filters remain review-only and do not pretend to rasterize image pixels;
- supported current-object soft-mask streams are still decoded and exposed through `soft_mask_stream` metadata;
- review-only calibrated JBIG2 rows retain `image_filter_boundary`, `image_filter_details`, default calibrated `/Decode`, soft-mask decode state, and stream notes.

`PdfImageRenderer::imageFilterDecodeParms()` now records bounded `/JBIG2Globals` metadata for review: source, object number, payload length, SHA-256, and preview hex.

`examples/wordpress-pdf-calibrated-jbig2-softmask-currentbase.php` emits a WordPress image review block for a CalRGB JBIG2 image with a decoded inverted soft mask and no raster execution.

## Verification

Baseline focused image gate before this patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php
3 test files, 562 assertions, 0 failures
```

Focused gate after this patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageCalibratedJbig2SoftMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php
4 test files, 611 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-calibrated-jbig2-softmask-currentbase.php
```

The smoke emitted `source_color_space=CalRGB`, `image_filters=[JBIG2Decode]`, `review_only_image_stream=true`, `jbig2_globals_present=true`, `jbig2_globals_object=90`, `soft_mask_decoded_with_current_filters=true`, `soft_mask_alpha_for_mid_sample=0.498039`, `decode_source=default-calibrated`, `output_color_mode=RGB`, and all Python/PDFium/PIL/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted calibrated sample preview alone, Indexed ICCBased JBIG2 palette metadata, inline JBIG2/ImageMask review, soft-mask `/Decode` opacity alone, soft-mask stream filter boundaries alone, DeviceN/Separation soft-mask previews, DCT CMYK `/Decode`, named color-space soft-mask resolution, or JPX/JBIG2 text-extraction filter exclusion. The bounded behavior is specifically calibrated image stream preview rows where JBIG2 remains review-only while current soft-mask bytes and JBIG2Globals metadata stay visible for WordPress review.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF value parser, image stream filter decoder, calibrated color-space planner, soft-mask alpha planner, and WordPress smoke path. Full live raster parity remains gated on pypdfium2/PDFium and PIL plus the broader markerPDF Python/model stack.
