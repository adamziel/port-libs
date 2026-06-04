# markerPDF Inline Image Tokenizer Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260604T085426Z`

Base accepted HEAD: `c0cb75e6d2aa1cad62de5c1d22985a09572b7ec3`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text extraction through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates visible text extraction to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates to pypdfium/PDFium text pages.

At that dependency boundary, `BI ... ID ... EI` inline image bytes are raster payload, not visible WordPress paragraph text. The native no-GPU tokenizer must therefore keep preview-only inline image filters closed across delimiter-looking payload bytes when it cannot raster-decode the image.

## Implementation

`PdfTextExtractor` now treats JBIG2 inline images with the standard JBIG2 file signature as an incomplete preview-only candidate until the final inline-image fallback boundary is reached. This mirrors the existing JPX/DCT early-`EI` deferral behavior while preserving the native no-raster boundary for JBIG2.

The focused fixture adds a `/JBIG2Decode` inline ImageMask whose payload begins with the JBIG2 file signature and then contains a fake ` EI BT ... Tj ET ...` sequence before the real inline-image terminator. Before this patch, that fake `EI` reopened text parsing and leaked `JBIG2 Inline Payload Noise` into the WordPress text lines. After the patch, only the before/after page text is imported.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
FAIL keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before JBIG2 Boundary',
  1 => 'After JBIG2 Boundary',
)
Actual: array (
  0 => 'Before JBIG2 Boundary',
  1 => 'JBIG2 Inline Payload Noise',
  2 => 'After JBIG2 Boundary',
)

1 test files, 19 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes

1 test files, 27 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1365 assertions, 0 failures
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

The smoke emitted `stray_bi_text_preserved=true`, `real_inline_image_payload_excluded=true`, `early_ei_payload_text_excluded_until_sample_boundary=true`, `preview_only_jbig2_payload_excluded_until_safe_boundary=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85/Flate DecodeParms validation, inline JPX/DCT framing, inline ImageMask preview rows, inline Indexed/JBIG2 review metadata, image XObject JBIG2/CCITT payload exclusion, object-stream inline-image repair, or stream-filter fail-closed behavior.

The new boundary is specifically content-tokenizer recovery for JBIG2 preview-only inline image payloads whose raster bytes contain delimiter-looking `EI` text before the safe inline-image boundary.

## Dependency Closure

No new support component is needed. This slice reuses the native content tokenizer, inline-image dictionary parser, stream filter metadata parser, JBIG2 review-only filter boundary, `PdfTextExtractor`, and WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
