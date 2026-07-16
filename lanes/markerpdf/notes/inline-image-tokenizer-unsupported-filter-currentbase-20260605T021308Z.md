# markerPDF Inline Image Tokenizer Unsupported Filter Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T021308Z`

Base accepted HEAD: `d806eae5e7b3b0986ea2580712cfcaab411d4a6c`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through `marker/pdf/extract_text.py` into parser-backed text extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster or encrypted payload, not WordPress paragraph text.

The native no-GPU PHP tokenizer already handles unfiltered sample floors, DCT/JPX/JBIG2/CCITT preview-only framing, filter chains, compact slash-delimited dictionaries, and malformed nested-dictionary decoys. This slice covers the remaining unsafe case where an inline image uses an unsupported filter such as `/Crypt`: delimiter-looking `EI` bytes inside undecodable image data must not reopen text parsing.

## Red First

Before the source edit, this red probe leaked payload text:

```text
array (
  0 => 'Before Unsupported Inline',
  1 => 'Unsupported Inline Payload Noise',
  2 => 'After Unsupported Inline',
)
```

The fixture used:

```text
BI /W 8 /H 1 /CS /G /BPC 8 /F /Crypt ID
abc EI BT /F1 12 Tf 72 660 Td (Unsupported Inline Payload Noise) Tj ET rawtail
EI
```

## Implementation

`PdfTextExtractor` now classifies inline-image filters that are neither natively decodable nor known preview-only raster filters as unsupported tokenizer boundaries. Unsupported filters are treated like open-ended preview-only payloads: non-empty candidates stay incomplete until the safer fallback terminator, so fake `EI BT ... Tj` bytes inside the image data remain excluded while the real text after the image survives.

The focused tests cover direct `/F /Crypt` payloads and wrapped `/F [/RL /Crypt]` payloads. The wrapped path reuses the existing `inlineImageBytesBeforePreviewFilter()` chain boundary, so native filters are decoded only up to the unsupported payload boundary.

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 108 assertions, 0 failures
```

Adjacent inline-image/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1575 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `unsupported_inline_filter_payload_excluded_until_safe_boundary=true`, keeps `After Unsupported Filter Boundary`, excludes `Unsupported Inline Payload Noise`, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, slash-delimited inline dictionaries, nested modifier-dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT/JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, raw JBIG2 segments, direct CCITT EOFB/RTC stream boundaries, object-stream inline-image repair, or image XObject payload exclusion.

The new behavior is specifically unsupported inline-image filters such as `/Crypt` at the content tokenizer boundary.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, stream filter parser, existing native decoders up to preview-only boundaries, `PdfTextExtractor`, focused lane tests, and the WordPress smoke path. Full upstream parity remains intentionally gated on live pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
