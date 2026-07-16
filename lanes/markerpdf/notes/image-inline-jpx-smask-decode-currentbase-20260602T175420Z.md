# markerPDF Inline JPX Soft-Mask Decode Current Base

Session: `port-dev-markerpdf-image38pdf-20260602T175420Z`
Micro-slice: `image-inline-jpx-smask-decode-currentbase-20260602T175420Z`
Base accepted HEAD: `1f51384b562639ecac3cfdac5c64ef58d0a7970f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF image rendering behind pypdfium/PIL:

- `marker/pdf/images.py::render_image()` renders a PDF page at `dpi / 72`, disables annotation drawing, converts to RGB, and `render_bbox_image()` crops the RGB image.
- `marker/images/extract.py::extract_page_images()` inserts image spans after layout-region matching, not as visible PDF text.
- `marker/pdf/extract_text.py::naive_get_text()` relies on pypdfium text pages, so inline-image bytes are not text spans.

Native PHP source truth for this slice is the parser boundary: inline JPX image bytes are image payloads. Delimiter-looking `EI` bytes inside a recognizable JPEG 2000 codestream must not close the inline image before the codestream EOC marker, and soft-mask streams referenced from the inline image dictionary are review metadata for RGB preview compositing, not raster execution.

## Behavior

`PdfTextExtractor::skipInlineImage()` now treats `JPXDecode` inline image payloads as verifiable when the candidate starts with a raw JPEG 2000 codestream or JP2 signature. Candidate `EI` markers before the JPEG 2000 EOC marker are rejected, preventing fake `BT ... Tj` text inside JPX bytes from leaking into WordPress paragraphs.

`PdfImageRenderer::inlineImageReviewPlan()` now records inline JPX review-only metadata and the soft-mask current-object boundary:

- `inline_image.soft_mask_present`
- `inline_image.soft_mask_source_object`
- `inline_image.soft_mask_uses_current_object_map`
- `inline_image.soft_mask_decoded_with_current_filters`
- `inline_image.soft_mask_decode_applied_before_rgb`
- notes for `inline_jpx_image_filter_review_only` and `inline_image_soft_mask_decoded_from_current_object`

The native preview boundary still does not decode JPX pixels or execute pypdfium/PIL. It decodes only the supported soft-mask stream filter chain, applies the mask `/Decode` alpha, and keeps the inline image payload out of visible text.

## Evidence

Red-first one-off before the patch:

- `php -r '...'` emitted `Before JPX`, `Inline JPX Noise`, and `After JPX`, proving the previous scanner accepted a fake `EI` before JPX EOC.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php`
  - `1 test files, 33 assertions, 0 failures`
  - PASS keeps inline JPX EI-looking bytes inside image payload before WordPress text import
  - PASS maps inline JPX soft-mask Decode from the current object map before RGB preview
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  - `1 test files, 398 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `1 test files, 597 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php`
  - `1 test files, 14 assertions, 0 failures`

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-inline-jpx-smask-decode-currentbase.php`
  - emitted `visible_text_imported=true`
  - emitted `excluded_inline_jpx_payload_text=true`
  - emitted `inline_jpx_review_only=true`
  - emitted `soft_mask_decoded_with_current_filters=true`
  - emitted `soft_mask_decode_applied_before_rgb=true`
  - emitted `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`

Syntax and integrity:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` passed.
- `php -l lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-jpx-smask-decode-currentbase.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused PHP PASS rows: `617 -> 619`.
- Mapped markerPDF behavior count: `450 -> 451 / 78`.
- Added WordPress smoke: `examples/wordpress-pdf-inline-jpx-smask-decode-currentbase.php`.

## Non-Overlap

This does not repeat accepted inline Indexed/JBIG2/ImageMask review, inline filter-array abbreviation/null-entry boundaries, Flate/LZW DecodeParms inline validation, ICC/DeviceN soft-mask stream previews, ColorKey soft-mask suppression, DCTDecode CMYK `/Decode`, JPX/JBIG2 XObject text exclusion, or generic stream-filter fail-closed work. The new behavior is specifically inline `JPXDecode` payload end-marker validation plus inline soft-mask `/Decode` current-object metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, inline image scanner, image renderer metadata planner, supported ASCIIHex/Flate filter decoders, and soft-mask `/Decode` sampler. Full upstream markerPDF runner parity remains gated on Python dependencies and runtime tools: pypdfium2/PDFium, PIL, pdftext, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI paths, benchmark tooling, and external OCR/PDF helpers.
