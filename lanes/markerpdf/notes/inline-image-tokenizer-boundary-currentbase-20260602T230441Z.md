# markerPDF Inline Image Tokenizer Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260602T230441Z`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating PDF syntax/token recovery to `pdftext.dictionary_output` and `pypdfium2` text pages:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

At that parser boundary, inline image payload bytes are image data, not visible text. The native PHP tokenizer also has to avoid the opposite false positive: a standalone malformed `BI` token that is not followed by inline image name/value dictionary entries and an `ID` operator must not consume later page text until the next real inline-image preamble.

## Behavior

`PdfTextExtractor::skipInlineImage()` now returns whether a real inline image was skipped. The content tokenizer and stale/missing stream-length scanner use that result to recover when a `BI` preamble is malformed or non-image-like instead of forcing end-of-stream.

The inline image preamble scanner now:

- rejects non-name tokens between `BI` and `ID` instead of skipping arbitrary content operators;
- requires canonical inline-image dictionary keys such as `/Width`, `/Height`, `/ColorSpace`, `/BitsPerComponent`, `/ImageMask`, `/Filter`, `/Decode`, `/DecodeParms`, or `/Interpolate`;
- preserves valid indirect inline-image values such as `/SMask 38 0 R`;
- keeps existing fail-closed behavior for real image dictionaries whose payload has no valid `EI`.

## Red-First Evidence

Before the fix, the focused probe and added test showed that a stray `BI` token consumed visible text until the next real inline image:

```text
Expected: Before Tokenizer Boundary, Stray BI Text Survives, After Tokenizer Boundary, After Real Inline Image
Actual:   Before Tokenizer Boundary, After Real Inline Image
```

The new focused test now preserves the malformed `BI` text and still excludes the real `BI /W ... ID ... EI` inline image payload.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
1 test files, 9 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 897 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
Emitted stray_bi_text_preserved=true, real_inline_image_payload_excluded=true, visible_text_imported=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted raw `BI / ID / EI` inline payload exclusion, inline abbreviation/DecodeParms candidate validation, filter-array null alignment, inline ImageMask preview rows, inline JPX soft-mask/ColorKey boundaries, object-stream inline-image filter repair, or missing/stale stream-owner inline payload decoy handling.

The new behavior is specifically tokenizer recovery for malformed/non-image `BI` preambles while preserving valid inline-image dictionary parsing, including indirect dictionary values.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, PDF value readers, inline-image dictionary parser, stream filter dispatcher, and WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and optional external OCR/rendering helpers.
