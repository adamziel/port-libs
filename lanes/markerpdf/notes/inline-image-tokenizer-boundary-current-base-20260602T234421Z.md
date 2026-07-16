# markerPDF inline image tokenizer boundary current base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260602T234421Z`

Base accepted HEAD: `7daebccdb1e231332676891328ab6455e928870a`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates page text to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates bounded page text to pypdfium/PDFium. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

At that upstream dependency boundary, inline image bytes are image payload, not visible text. A native content tokenizer must therefore keep `BI ... ID ... EI` image bytes out of WordPress paragraphs, including delimiter-looking `EI` bytes that occur before the image sample boundary.

## Implementation

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now uses the existing `/Width`, `/Height`, `/ColorSpace`, `/BitsPerComponent`, and `/ImageMask` parsing to infer the raw decoded byte count for unfiltered inline images. When that count is known, an unfiltered candidate is accepted only after it has consumed at least that many image bytes.

This preserves the existing permissive fallback for malformed/incomplete unfiltered payloads after enough bytes have been consumed, while preventing an early payload ` EI ` sequence from reopening text-token parsing inside image data.

## Red First

The new fixture adds an unfiltered DeviceGray inline image with `/W 16 /H 1 /BPC 8`. Its payload starts with `abc EI`, then contains text-looking PDF operators and `rawtail`, followed by the real `EI` terminator.

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
FAIL keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Early EI Boundary',
  1 => 'After Early EI Boundary',
)
Actual: array (
  0 => 'Before Early EI Boundary',
  1 => 'Early EI Inline Payload Noise',
  2 => 'After Early EI Boundary',
)

1 test files, 10 assertions, 1 failures
```

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied

1 test files, 18 assertions, 0 failures
```

Adjacent inline-image/parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 792 assertions, 0 failures
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

The smoke emitted `stray_bi_text_preserved=true`, `real_inline_image_payload_excluded=true`, `early_ei_payload_text_excluded_until_sample_boundary=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then rendered five Gutenberg paragraphs with the early-EI payload text and `rawtail` excluded.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, ordinary inline image payload exclusion, inline image abbreviation/DecodeParms handling, JPX `EI`-looking payload recovery, object-stream inline-image filter helper recovery, stale/missing content stream owner recovery, or image preview metadata slices.

The new behavior is specifically unfiltered inline-image tokenizer recovery when an `EI` delimiter-looking token appears before the inferred raw sample byte boundary.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP content tokenizer, inline-image dictionary parser, stream filter metadata parser, image sample-size inference, text extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
