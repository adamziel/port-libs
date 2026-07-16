# Inline Image Tokenizer Dot-Numeric Tail Current Base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T163545Z`

Accepted base: `989d72297d7b2e126aa296fdd7e44238e330f68d`

## Source Truth

- Upstream markerPDF keeps searchable PDF text extraction distinct from image payload handling in the native/PDFium text extraction path before OCR/model fallback.
- The accepted PHP port already accepts dot-leading real numbers in content-stream numeric operands via `PdfTextExtractor::numericOperand()`.
- This slice aligns inline-image malformed dictionary tail recovery with that numeric grammar so a valid real like `.5` does not make the `BI ... ID ... EI` tokenizer reject the dictionary and leak image bytes as WordPress text.

## Red-First Evidence

Before the source change, a focused local probe using:

`BI /W 1 /H 1 /CS /G /BPC 8 /D [1 0] .5 /F /MalformedPreview ID`

returned text lines:

`["Before Dot Tail Inline","Dot Tail Inline Noise","After Dot Tail Inline"]`

The `Dot Tail Inline Noise` line came from bytes after a delimiter-looking `EI` inside the inline image payload.

## Change

- `PdfTextExtractor::inlineImageMalformedDictionaryTailOperandToken()` now accepts dot-leading real operands with the same numeric grammar used by `numericOperand()`.
- `PdfImageRenderer::inlineImageMalformedDictionaryTailOperandToken()` uses the same grammar so review-only metadata reports the malformed tail boundary consistently.
- Added a focused PDF fixture that proves visible text import keeps only the before/after text while excluding `.5 /F`, `MalformedPreview`, raw payload bytes, and a fake text object embedded in the image payload.
- Added a WordPress smoke that emits only the two expected Gutenberg paragraphs and records `dot_numeric_tail_payload_excluded=true`, `dot_numeric_tail_dictionary_operand_review_only=true`, `dot_numeric_tail_preview_failed_closed=true`, and no Python/model/external-tool execution.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php`
  - `1 test files, 18 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerTextPositionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerPathConstructionBoundaryCurrentBaseTest.php`
  - `8 test files, 1873 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-dot-numeric-tail-currentbase.php`
  - exits 0 and outputs only `Before Dot Tail Inline` and `After Dot Tail Inline` paragraphs.

## Non-Overlap

This does not repeat accepted inline image decode/EOD, DecodeParms tail, malformed filter operand, text-positioning, path-construction, DCT, JPX, CCITT, JBIG2, or preview-only fallback cases. It only owns dot-leading numeric operands in the malformed inline image dictionary tail boundary.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP content tokenizer, inline image review plan, and focused lane test harness. No OCR, Surya, Texify, Torch, pypdfium/PIL raster decode, live model worker, external PDF utility, or provider service was run.
