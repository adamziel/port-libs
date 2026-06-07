# markerPDF inline image Decode exponent/comment boundary current-base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260607T020043Z`
Base accepted HEAD: `d711743ebd52c0644d6c028bf2a3daee08098182`

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction on parser-backed PDF content handling before any OCR/model fallback. Inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload; following content operators after the real terminator remain searchable text for WordPress import.

Under the current no-GPU markerPDF scope, this PHP lane owns the native parser equivalent for inline image dictionary value parsing, stream-filter end boundaries, image Decode metadata, and WordPress paragraph safety without running PDFium, PIL, Python models, OCR, or external PDF tools.

## Behavior

`PdfImageRenderer` now accepts exponent-form numeric operands in inline image `/Decode` arrays, including direct grayscale and ImageMask cases such as `/D [0 5e-1]` and `/D [5e-1 0e0]`. The PDF value reader also rejects partial bare-number prefixes, so malformed tokens like an exponent suffix split after the mantissa cannot be silently interpreted as a different Decode range.

`PdfTextExtractor` now validates the raw post-filter bytes before accepting the current inline image `EI` after a native filter decode. PDF comments after a native filter EOD are treated as whitespace only when they are closed by a line break before the current `EI`; an `EI` inside an unterminated comment no longer prematurely closes the inline image and leaks payload text into WordPress paragraphs.

The WordPress smoke updates the existing inline image Decode boundary example with exponent-form Decode operands and verifies the ASCIIHex EOD-comment payload remains excluded from Gutenberg paragraphs.

## Red First

Before the source fix, the direct focused run failed both the exposed existing EOD-comment case and the new exponent Decode case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 858 assertions, 2 failures
```

The failures were:

- `treats PDF comments after inline native filter EOD as whitespace before real EI boundaries` leaked `Inline EOD Comment Noise`.
- `resolves exponent-form inline image Decode numbers before preview rows` treated `/D [5e-1 0e0]` as an invalid ImageMask Decode array.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 887 assertions, 0 failures
```

Adjacent inline image renderer/tokenizer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 2202 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php --self-test
```

Result: exit 0. Emitted metadata includes `asciihex_comment_eod_payload_excluded_until_real_ei=true`, `exponent_inline_decode_preview_accepted=true`, `exponent_inline_decode_decoded_gray=0.5`, `exponent_inline_mask_decode_preview_accepted=true`, `exponent_inline_mask_opacity=[0,0.5]`, `exponent_inline_decode_payload_excluded=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline tokenizer scientific numeric graphics prefixes, invalid/duplicate/null/malformed Decode operands, direct comment/literal/hex Decode decoys, generation-exact indirect Decode operands, DecodeParms validation, null filters, Image XObject Decode behavior, JPX/DCT/CCITT/JBIG2 review-only filters, xref repair, metadata extraction, annotations, forms, page geometry, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior here is exponent-form inline image `/Decode` numeric value parsing plus native-filter post-EOD comment boundary validation for the selected inline image terminator.

## Dependency Closure

No new support component is needed. This slice reuses native PHP content-stream tokenization, PDF value parsing, stream filter end detection, inline image preview metadata, text extraction, and the existing WordPress smoke path. Live OCR, Surya/Torch/Texify, PDFium/PIL raster parity, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
