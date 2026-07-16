# markerPDF Inline Image Tokenizer Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T053609Z`

Base accepted HEAD: `02007d3960cfef30d95378048f7d709ebc53dad0`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed page text before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while marked-content `/ActualText` and `/Alt` replacements are accessible source text that should be available to WordPress paragraph import.

This no-GPU native slice keeps the inline-image tokenizer closed across delimiter-looking raster bytes but closes the preview-only fallback before a following direct marked-content replacement dictionary when the candidate `EI` is inside `/ActualText` or `/Alt` literal text.

## Implementation

`PdfTextExtractor::skipInlineImage()` now checks preview-only fallback segments with a token-aware marked-content dictionary scanner. The scanner only triggers when a name token is followed by a direct dictionary whose top-level `/ActualText` or `/Alt` literal is unterminated at the candidate `EI`.

This preserves the existing JBIG2/CCITT/unsupported preview-only image boundaries while preventing the tokenizer from resuming in the middle of a marked-content replacement dictionary.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL closes preview-only inline image fallback before marked ActualText containing EI bytes (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Marked ActualText EI',
  1 => 'Visible EI ActualText',
  2 => 'After Marked ActualText EI',
)
Actual: array (
  0 => 'Before Marked ActualText EI',
  1 => 'Hidden ActualText Source',
  2 => 'After Marked ActualText EI',
)

1 test files, 145 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS closes preview-only inline image fallback before marked ActualText containing EI bytes
...
1 test files, 154 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1678 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `preview_only_marked_actualtext_ei_preserved_after_safe_boundary=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, ASCII85/Flate/LZW/RunLength DecodeParms validation, inline JPX/DCT/JBIG2/CCITT preview-only framing, named ColorSpace fallbacks, unsupported-filter payload closure, object-stream inline-image repair, stream-filter fail-closed behavior, or generic marked-content replacement extraction.

The bounded new behavior is specifically tokenizer fallback selection when the text following a preview-only inline image starts with a direct marked-content replacement dictionary whose `/ActualText` or `/Alt` literal contains `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, preview-only image filter boundary logic, marked-content replacement extraction, `PdfTextExtractor`, and the existing WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
