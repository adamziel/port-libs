# markerPDF inline image tokenizer vertical-tab boundary current base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T150435Z`

Accepted base: `a675a52a65b31d9c3ca517be37130b1323ff7290`

## Source truth

Upstream markerPDF keeps searchable-PDF text extraction on the parser-backed path before any OCR/model fallback. For the native no-GPU port, inline image tokenization must therefore follow PDF lexical token boundaries instead of generic language whitespace.

PDF whitespace is the exact set NUL, horizontal tab, line feed, form feed, carriage return, and space. Vertical tab is not a PDF whitespace character. A content stream beginning `BI\v/W ... ID` is a malformed BI-like token, not a valid inline image dictionary preamble, so following text must remain visible to WordPress import.

## Change

`PdfTextExtractor::isPdfWhitespace()` now uses the exact PDF whitespace set instead of `ctype_space()`. This preserves the accepted NUL-separated inline image behavior while rejecting vertical tab as a BI dictionary separator.

The focused tokenizer test adds a malformed vertical-tab BI preamble where `Vertical Tab BI Text Survives` must remain in `extractTextLines()`, `extractTextRuns()`, `extractPlainText()`, and `naiveGetText()`. The WordPress smoke now emits `pdf_vertical_tab_not_inline_image_separator=true`.

## Red first

Before the production fix, the new focused test failed because the tokenizer treated vertical tab as whitespace and swallowed the following text as inline image payload:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 508 assertions, 1 failures
FAIL does not treat PDF vertical tab as an inline image tokenizer separator before WordPress text extraction
Actual: array (
  0 => 'Before Vertical Tab Boundary',
  1 => 'After Vertical Tab Boundary',
)
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result: `1 test files, 517 assertions, 0 failures`

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
```

Result: `10 test files, 1511 assertions, 0 failures`

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

Selected smoke flags: `pdf_vertical_tab_not_inline_image_separator=true`, `pdf_null_whitespace_inline_payload_excluded=true`, `compact_slash_delimited_inline_image_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `2587 -> 2588`
- `wordpressScenarios`: `2191 -> 2192`
- Focused tokenizer assertions: `507 -> 517`
- Manifest `pdfInlineImageTokenizerBoundaryCurrentBaseBehaviors`: `1 -> 2`

## Non-overlap

This slice does not repeat the accepted malformed BI preamble, tight ID, comment-after-ID, NUL whitespace, compact slash-delimited dictionary, preview-filter, JBIG2 sample-floor, marked-content, graphics-state, Type3 metric, inline Decode array, or image renderer/decode behavior. It owns only the vertical-tab non-whitespace lexical boundary for inline image tokenization.

## Dependency closure

No new support component is needed. The patch reuses the native PHP content tokenizer, text extractor, inline image boundary scanner, and existing WordPress smoke renderer. No Python, OCR, Surya/Texify/Torch, pypdfium, PIL, external PDF tools, live services, or GPU/model execution were run.
