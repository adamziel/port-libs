# markerpdf image XObject SMask filter current base

Micro-slice: `image-xobject-smask-filter-currentbase-20260602T1324Z`

Base accepted HEAD: `8222e6d278bf50a168a1fbef8aa9e27f100cc5f3`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes image rendering through `marker/pdf/images.py`: `render_image()` renders a PDF page through pypdfium with annotations disabled, converts the PIL image to RGB, and `render_bbox_image()` crops before converting to RGB. The native PHP port cannot execute pypdfium/PIL in this lane, so this slice records the parser-side image XObject soft-mask filter boundary needed before RGB preview compositing.

Source URL inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now exposes a top-level `soft_mask_filter_boundary` review payload for `/SMask` image XObjects. It resolves the current object-map `/SMask` stream, records the mask source object, filter chain, preview-only filters, raw byte length, decoded byte length/hash/sample bytes, and fail-closed decode status.

Supported native review decoders for this boundary are `ASCIIHexDecode`, `ASCII85Decode`, `RunLengthDecode`, and `FlateDecode` with bounded predictor handling. JPX/JBIG2/CCITT/DCT and unknown filters stay preview-only or fail-closed instead of pretending PHP rasterized mask pixels.

## Red First

Before the production edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`

failed in the new focused case because `soft_mask_filter_boundary` was missing.

## Verification

`php -l lanes/markerpdf/src/PdfImageRenderer.php`

Result: no syntax errors.

`php -l lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: no syntax errors.

`php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-smask-filter-currentbase.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: `1 test files, 149 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-smask-filter-currentbase.php`

Result: emitted `soft_mask_filters=["ASCIIHexDecode","FlateDecode"]`, `soft_mask_decoded_length=3`, `soft_mask_decoded_sample_bytes=[0,127,255]`, `uses_current_object_map=true`, `stale_mask_object_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted base image `/Decode`, `/ImageMask` stencil opacity, ICCBased soft-mask metadata, soft-mask `/Decode` opacity, DCTDecode CMYK, Indexed ICC/JBIG2 soft-mask review, text-extractor image-filter exclusion, inline-image boundaries, or stream-filter text extraction slices. It only adds the image-rendering review boundary for the soft-mask image stream's own filter chain and decoded current-mask bytes.

## Dependency Closure

No new support component is needed. The slice reuses native PHP string parsing and zlib functions already available in the lane. Full upstream raster parity remains dependency-gated on pypdfium/PIL/model execution, but this review boundary runs without Python, models, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools.

## Next

Continue with non-overlapping image/color-space, parser, metadata, page/action, annotation, AcroForm, object-stream/xref, supplied-table/OCR, and security/DSS edges that can pass focused markerPDF PHP tests on current base.
