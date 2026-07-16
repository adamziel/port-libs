# markerPDF Inline Image Tokenizer Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T000052Z`

Base accepted HEAD: `738826c08076f3957b642ba5c058778fae6cf29d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, where page text is delegated to `pdftext.extraction.dictionary_output(...)` and pypdfium/PDFium text pages. At that dependency boundary, `BI ... ID ... EI` inline image bytes are raster payload, not visible WordPress paragraph text.

The native no-GPU tokenizer must therefore exclude delimiter-looking image payload bytes without swallowing real searchable text between adjacent inline images.

## Implementation

`PdfTextExtractor::skipInlineImage()` now keeps the existing fail-closed fallback for open-ended preview-only filters, but stops extending that fallback across a subsequent real inline-image preamble once the previous candidate has reached its expected sample floor. The segment scanner respects strings, hex strings, arrays, dictionaries, names, and comments before recognizing `BI ... ID` image dictionaries.

This closes the current-base gap left by the prior CCITT boundary note: two preview-only `/CCITTFaxDecode` and `/CCF` inline images in one content stream no longer swallow visible text between them.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps preview-only inline image filter chains closed before WordPress text extraction
PASS keeps CCITTFax preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps CCF abbreviated inline image payload closed across delimiter-looking EI bytes
FAIL preserves text between multiple CCITT inline image tokenizer fallbacks (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before First CCITT',
  1 => 'Between CCITT Images',
  2 => 'After Second CCITT',
)
Actual: array (
  0 => 'Before First CCITT',
  1 => 'After Second CCITT',
)

1 test files, 55 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps preview-only inline image filter chains closed before WordPress text extraction
PASS keeps CCITTFax preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps CCF abbreviated inline image payload closed across delimiter-looking EI bytes
PASS preserves text between multiple CCITT inline image tokenizer fallbacks

1 test files, 64 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1414 assertions, 0 failures
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

The smoke emitted `multiple_preview_only_ccitt_text_between_images_preserved=true`, `preview_only_ccitt_payload_excluded_until_safe_boundary=true`, `wrapped_preview_filter_chain_text_preserved=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85/Flate DecodeParms validation, inline JPX/DCT/JBIG2 framing, inline ImageMask preview rows, inline Indexed/JBIG2 review metadata, Image XObject CCITT/JBIG2 payload exclusion, object-stream inline-image repair, stream-filter fail-closed behavior, single CCITT/CCF tokenizer recovery, or wrapped JPX/JBIG2 filter-chain recovery.

The new bounded behavior is specifically preserving visible text between multiple open-ended preview-only inline-image tokenizer fallbacks in one content stream.

## Dependency Closure

No new support component is needed. This slice reuses the native content tokenizer, inline-image dictionary parser, stream filter metadata parser, expected sample-length calculator, CCITT/JBIG2 review-only filter boundaries, `PdfTextExtractor`, and the existing WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
