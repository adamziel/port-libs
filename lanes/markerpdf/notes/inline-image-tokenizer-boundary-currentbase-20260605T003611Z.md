# markerPDF Inline Image Tokenizer Raw JBIG2 Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T003611Z`

Base accepted HEAD: `20dfe2be1051f3aa7ba6cdf25cd8a0bf19059ec8`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium text extraction. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, not visible WordPress paragraph text.

The prior accepted tokenizer slice kept JBIG2 payloads closed when the bytes started with the JBIG2 file signature. Real PDF `/JBIG2Decode` inline images may also contain raw JBIG2 segment bytes without that file header, so a delimiter-looking `EI` sequence inside raw segment bytes must not reopen text parsing.

## Implementation

`PdfTextExtractor::inlineJbig2CandidateState()` now treats any non-empty `/JBIG2Decode` inline-image candidate as an incomplete preview-only image candidate. The tokenizer therefore defers delimiter-looking `EI` bytes until the final inline-image fallback boundary, matching the existing no-raster boundary for JBIG2 while preserving following page text.

The focused fixture adds a raw-segment `/JBIG2Decode` inline ImageMask payload containing ` EI BT ... Tj ET ...` before the real inline-image terminator. The payload text is excluded from `extractTextLines()`, `extractTextRuns()`, `extractPlainText()`, and `naiveGetText()`, while text after the real `EI` remains visible.

## Red First

Before the source change, a direct raw JBIG2 fixture leaked payload text:

```text
array (
  0 => 'Before Raw JBIG2 Boundary',
  1 => 'Raw JBIG2 Inline Payload Noise',
  2 => 'After Raw JBIG2 Boundary',
)
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps raw JBIG2 segment inline image payload closed across delimiter-looking EI bytes
PASS keeps preview-only inline image filter chains closed before WordPress text extraction
PASS keeps CCITTFax preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps CCF abbreviated inline image payload closed across delimiter-looking EI bytes
PASS preserves text between multiple CCITT inline image tokenizer fallbacks

1 test files, 72 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1491 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emitted `raw_jbig2_segment_payload_excluded_until_safe_boundary=true`, `preview_only_jbig2_payload_excluded_until_safe_boundary=true`, `real_inline_image_payload_excluded=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, inline JPX/DCT framing, JBIG2 file-header boundary handling, RunLength-wrapped preview-filter chains, CCITT/CCF fallback boundaries, inline image review metadata, object-stream inline-image repair, stream-filter fail-closed behavior, DCTDecode filter boundaries, or CCITT DecodeParms renderer metadata.

The new boundary is specifically raw JBIG2 segment inline-image payloads without the JBIG2 file header.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, preview-only JBIG2 image boundary, `PdfTextExtractor`, and WordPress smoke path. Full upstream model/raster parity remains gated on live pdftext, pypdfium2/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
