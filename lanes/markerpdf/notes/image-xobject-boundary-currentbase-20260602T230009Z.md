# markerPDF Image XObject Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260602T230009Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260602T230009Z`
Base accepted HEAD: `1c11c94b45001e6d7041475e1155fe1067d73191`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates page text extraction from page/image rendering:

- `marker/pdf/extract_text.py` routes visible text through PDFium text pages and `pdftext.extraction.dictionary_output`.
- `marker/pdf/images.py` renders page/crop images through PDFium, disables annotation drawing, converts the PIL result to RGB, and returns image data outside the text pipeline.

The native PHP port does not execute PDFium, PIL, pdftext, Python models, Poppler, Ghostscript, OCR, or external PDF tools. This slice maps the same boundary by exposing page resource image XObjects as review-only metadata while keeping raster stream bytes out of WordPress paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now reports current page image XObject review rows:

- walks page tree resource inheritance to find `/Resources /XObject` image streams;
- counts page content-stream `Do` invocations by decoded resource name, including escaped names such as `/Im#20A`;
- records object number, page index, dimensions, color space, image mask state, soft-mask object, filter chain, preview-only filters, raw length, decoded length, and decoded hash when native filters are safe;
- excludes Form XObject resources from this image review and keeps all stream payload bytes out of the review JSON and visible text;
- fails closed for encrypted PDFs.

`examples/wordpress-pdf-image-xobject-boundary-currentbase.php` emits WordPress paragraph blocks plus a review comment proving the invoked `/Hero#20Image` XObject is counted, the ASCIIHex/Flate image stream is decoded only to hash/length metadata, and image payload text is not visible.

## Verification

Focused baseline/adjacent extractor gate before the slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 597 assertions, 0 failures
```

Focused current-base gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 61 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted `image_xobject_count=1`, `invoked_image_xobject_count=1`, `first_resource_name="Hero Image"`, `first_image_filters=["ASCIIHexDecode","FlateDecode"]`, `first_image_decoded_with_current_filters=true`, `payload_in_visible_text=false`, and Python/model/external-tool execution flags false.

## Status Delta

- Behavior tests: `945 -> 948` pass / `0` fail.
- Focused assertion growth: new direct current-base test file adds `61` assertions.
- WordPress smoke: added `wordpress-pdf-image-xobject-boundary-currentbase.php`.
- Mapped semantics: unchanged at `664 / 78`; this refines the existing `pdfImageXObjectBoundaryBehaviors` upstream row with current-base page-resource review metadata.

## Non-overlap

This does not repeat accepted inline image payload exclusion, JPX/JBIG2/DCT/CCITT stream exclusion, image color-space/soft-mask/Decode preview rows, inline JPX ColorKey output rows, object-stream filter ownership, page content array resource-stack handling, Form XObject text expansion, or generic fallback image-stream skipping. The new behavior is specifically current page `/Resources /XObject` image review metadata plus `Do` invocation counting while preserving text isolation.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page tree/resource inheritance helpers, content token parser, stream filter decoder, image stream recognizer, encryption preflight, and WordPress smoke path. Full live raster parity remains gated on PDFium/PIL or a future native raster backend; this patch does not execute external PDF tooling.
