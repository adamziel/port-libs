# markerPDF Inline Image Tokenizer Named ColorSpace Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T031950Z`

Base accepted HEAD: `ade3bedea1d5f41d2a42f4498c3f970f11a0b9a1`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through parser-backed PDF extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while text after the real inline-image terminator remains visible document text.

The native no-GPU tokenizer already handles malformed BI preambles, unfiltered exact sample floors, compact slash-delimited dictionaries, nested modifier-dictionary decoys, preview-only JPX/JBIG2/DCT/CCITT filters, unsupported filters, and malformed filter operands. This slice covers valid inline images whose `/ColorSpace` is a named page resource such as `/CSWordPress`. The tokenizer cannot derive an exact component count from that content stream dictionary alone, so it must not accept the first delimiter-looking `EI` as a verified image end.

## Red First

Before the source change, the focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps named ColorSpace inline image payload closed before WordPress text extraction (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Named ColorSpace Inline',
  1 => 'After Named ColorSpace Inline',
)
Actual: array (
  0 => 'Before Named ColorSpace Inline',
  1 => 'Named ColorSpace Inline Payload Noise',
  2 => 'After Named ColorSpace Inline',
)

1 test files, 118 assertions, 1 failures
```

The fixture uses `/Resources << /ColorSpace << /CSWordPress /DeviceRGB >> >>` and an inline image dictionary:

```text
BI /W 16 /H 1 /CS /CSWordPress /BPC 8 ID
abc EI BT /F1 12 Tf 72 660 Td (Named ColorSpace Inline Payload Noise) Tj ET rawtail
EI
```

## Implementation

`PdfTextExtractor` now treats unfiltered inline images with declared geometry and a named or otherwise unresolved `/ColorSpace` as an open-ended tokenizer boundary. It uses the one-component minimum byte floor only to decide whether a fallback can close before the next inline image; it does not treat that minimum as an exact decoded length.

Exact unfiltered image dictionaries with known `/DeviceGray`, `/DeviceRGB`, `/DeviceCMYK`, `/Indexed`, or `/ImageMask` sample lengths keep the existing exact sample-floor behavior.

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 126 assertions, 0 failures
```

Adjacent inline-image/parser/image-renderer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 999 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `named_colorspace_inline_payload_excluded_until_safe_boundary=true`, preserves `After Named ColorSpace Boundary`, excludes `Named ColorSpace Inline Payload Noise`, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, exact unfiltered sample-length validation, slash-delimited inline dictionaries, nested modifier-dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT/JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, visible `EI` text after preview-only fallback, unsupported `/Crypt` filter boundaries, malformed or unresolved filter operands, direct CCITT EOFB/RTC stream boundaries, object-stream inline-image repair, image XObject payload exclusion, or stream-owner `endstream` decoy recovery.

The new behavior is specifically named-resource `/ColorSpace` inline image tokenizer boundaries where the native content stream parser cannot compute an exact component count.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, existing PDF value readers, focused lane tests, and the WordPress smoke path. Full upstream runner parity remains gated on live pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
