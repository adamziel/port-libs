# markerPDF DCTDecode Nested Mask Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T172910Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T172910Z`
Base accepted HEAD: `3def3c127d89fb2d9ff534915066695347ee7763`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable page text through `marker/pdf/extract_text.py`, delegating low-level PDF stream and text-page boundaries to pdftext/PDFium before model stages.
- Upstream image rendering is separate in `marker/pdf/images.py::render_image()`, where PDFium/PIL rasterize PDF image data and convert previews to RGB. In the native no-GPU PHP port, DCTDecode/JPEG payload bytes remain review-only image data.
- PDF DCTDecode streams are JPEG data. JPEG EOI `FF D9` closes the preview payload; post-EOI bytes before `endstream` are not WordPress paragraph text.

Source references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

Primary Image XObject and alternate image reviews already clipped DCTDecode review bytes at JPEG EOI. Nested `/SMask` and explicit `/Mask` image stream reviews still reported the full stream payload length, including post-EOI surplus text-looking bytes.

`PdfTextExtractor` now routes nested soft-mask and explicit-mask image streams through the same DCT-aware review-byte boundary before reporting `raw_length`. The payload stays review-only, `decoded_with_current_filters=false`, and visible WordPress text keeps only the surrounding searchable paragraphs.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clips nested DCTDecode soft-mask and explicit-mask review bytes at JPEG EOI boundaries
Values are not identical
Expected: 25
Actual: 84

1 test files, 14 assertions, 1 failures
```

## Verification

Focused test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS clips nested DCTDecode soft-mask and explicit-mask review bytes at JPEG EOI boundaries

1 test files, 36 assertions, 0 failures
```

Adjacent DCT/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
7 test files, 1560 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-mask-boundary-currentbase.php
```

The smoke emits `soft_mask_post_eoi_surplus_clipped=true`, `explicit_mask_post_eoi_surplus_clipped=true`, `nested_mask_surplus_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode stream EOI recovery, NUL padding, prefix Flate/LZW/RunLength/ASCIIHex DCT boundaries, inline DCT tokenizer framing, DCT CMYK/YCCK Adobe transform planning, DCT `/Decode` sample review, alternate-image DCT clipping, malformed filter operands, post-DCT filter review, CCITT/JPX/JBIG2 preview-only filters, or broad stream filter stack recovery.

The bounded behavior is specifically nested DCTDecode `/SMask` and explicit `/Mask` image-stream review metadata using the same JPEG EOI boundary as primary image XObjects.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary parser, image XObject review path, DCT JPEG EOI boundary helper, focused tests, and WordPress smoke renderer.

Full raster parity remains dependency-gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
