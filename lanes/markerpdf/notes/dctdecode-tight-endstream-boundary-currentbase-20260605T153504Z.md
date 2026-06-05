# markerPDF DCTDecode Tight Endstream Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T153504Z`

Base accepted HEAD: `873807440756fc8704879c8711097a87bb389c5e`

## Source Truth

Upstream `sddai/markerPDF` keeps searchable PDF text extraction separate from image rendering. DCT/JPEG bytes are image payloads passed to the image path, not text-block input. In this no-GPU PHP lane, `/DCTDecode` remains preview-only, but native stream parsing still has to preserve the real PDF stream boundary so image bytes and fake payload tokens do not become WordPress paragraphs.

Relevant upstream boundary:

- `marker/pdf/images.py::render_image()` handles PDF image payloads through the image rendering path before RGB conversion.
- `marker/pdf/extract_text.py` obtains text blocks from parser text, not from JPEG payload bytes.

Native PHP does not execute PDFium, PIL, OCR, Torch, Surya/Texify, or external PDF tools for this slice.

## Behavior

This slice tightens the direct `/DCTDecode` boundary where a stale `/Length` lands immediately after JPEG EOI and the next bytes spell `endstream` without a preceding line terminator. That tight token is image payload content, not a valid stream terminator line for this recovery path.

`PdfTextExtractor` now:

- rejects DCT EOI-adjacent `endstream` tokens unless `endstream` starts after `\n` or `\r`;
- recovers DCT object and stream payload boundaries to the next line-bound `endstream`, keeping fake objects inside the image stream;
- still clips recovered DCT review bytes back to the JPEG EOI payload for image XObject metadata.

`PdfImageRenderer` now applies the same line-bound DCT preview terminator predicate before ICCBased/direct image review rows, so renderer metadata agrees with text extraction.

## Red-First Evidence

Before the patch, a PHP probe with a DCT image stream containing `\xff\xd9endstream` followed by a fake text stream leaked the fake text:

```text
array (
  0 => 'Before tight DCT',
  1 => 'Tight DCT Leak',
  2 => 'After tight DCT',
)
Before tight DCT
Tight DCT Leak
After tight DCT
```

After implementation, the same probe returns only:

```text
array (
  0 => 'Before tight DCT',
  1 => 'After tight DCT',
)
Before tight DCT
After tight DCT
```

## Verification

Focused DCT file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 437 assertions, 0 failures
```

Adjacent DCT/image renderer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1050 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-tight-endstream-boundary-currentbase.php
```

The smoke emits `jpeg_eoi_tight_endstream_token_rejected=true`, `raw_length_after_boundary_recovery=27`, `dctdecode_image_payload_excluded_from_text=true`, `xobject_preview_only_filters=["DCTDecode"]`, `xobject_native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT review-only filter metadata, DCT alias metadata, ColorTransform/DecodeParms review, direct fake-`endstream` before JPEG EOI recovery, NUL-padded EOI boundaries, malformed lenient EOI recovery, missing-length recovery, overdeclared-length recovery before later objects, Flate/ASCIIHex/RunLength native-prefix boundaries, null filter slots, indirect filter owner recovery, unsupported-prefix recovery, Crypt Identity handling, malformed filter operands, inline DCT tokenization, or post-EOI surplus clipping.

The new bounded behavior is specifically direct `/DCTDecode` streams where JPEG EOI is followed immediately by the bytes `endstream` without a line terminator before the keyword.

## Dependency Closure

No new support component is needed. This reuses the native PDF stream parser, object-boundary scanner, DCT/JPEG EOI scanner, image XObject review rows, direct renderer review rows, focused tests, and WordPress smoke path. Full live JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; live OCR/model execution remains intentionally out of scope.
