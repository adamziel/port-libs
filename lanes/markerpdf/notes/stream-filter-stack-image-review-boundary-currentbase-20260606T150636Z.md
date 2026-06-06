# markerPDF Image XObject stream-filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T150636Z`

## Source Truth

- Upstream `sddai/markerPDF` separates searchable page text extraction from image rendering and raster handoff. Under this no-GPU markerPDF lane, the PHP port owns the native PDF parser boundary that decides whether an Image XObject stream is safe to decode or stays review-only.
- PDF stream filter stacks must consume the current stream bytes cleanly. A Flate member followed by non-whitespace bytes inside the declared stream boundary is not a clean native image payload and must not be treated as raster-decodable metadata.

## Behavior

This current-base slice applies the existing strict stream-filter end boundary to Image XObject review streams, including primary images, soft masks, explicit masks, alternate images, and metadata streams. Image review now fails closed when `/Length` includes raw non-whitespace bytes after a compressed Flate member:

```text
/Filter /FlateDecode
stream
  <valid zlib member>BT ... (tail payload) ... ET
endstream
```

The tailed image still appears as review metadata with its raw length and filter stack, but `decoded_with_current_filters=false`, decoded hash/length are null, and `native_raster_decode=false`. A clean Flate image in the same fixture still decodes normally.

## Red Probe

After adding the focused fixture and before the source change, the new test failed because the tailed image was still reported as decoded:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on Flate image review streams with non-whitespace bytes after the compressed member
Expected: false
Actual: true
1 test files, 10 assertions, 1 failures
```

## Evidence

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on Flate image review streams with non-whitespace bytes after the compressed member
1 test files, 21 assertions, 0 failures
```

Adjacent stream/filter boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1631 assertions, 0 failures
```

Auxiliary image review paths touched by the stricter decode calls:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNIccSmaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 288 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `unsafe_image_filter_tail_rejected=true`, `clean_image_filter_preserved=true`, `native_raster_decode_blocked_for_unsafe_tail=true`, `visible_text_preserved=true`, `payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`, and renders only the two visible page paragraphs.

## Non-Overlap

This does not repeat accepted text-stream stack decoding, attachment stack decoding, all-null filters, compact DecodeParms arrays, extra DecodeParms rejection, private Crypt filter rejection, object-stream/xref-stream filter repair, inline-image tokenizer boundaries, DCT/CCITT preview-only image filters, image optional-content review, image placement geometry, AcroForm xref generation repair, or live OCR/model behavior.

The bounded behavior is specifically Image XObject review streams whose declared stream bytes contain non-whitespace trailing payload after a Flate member.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, stream filter decoder, strict filter-end boundary enforcement, Image XObject review metadata path, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
