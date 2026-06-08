# markerPDF Inline Image Tokenizer Name Tail Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T171659Z`

Base accepted HEAD: `a783f1c240f4f9420855f587c7aca332f110038d`

## Source Truth

Upstream `sddai/markerPDF` keeps searchable-PDF text extraction separate from OCR/model raster stages. At this native no-GPU boundary, inline image bytes between `BI`/`ID`/`EI` are image payload, not visible text, even when malformed dictionary tails force review-only image handling.

PDF inline image dictionaries can contain implementation-specific keys, but a top-level dangling name immediately before `ID` is not visible text. The safe import behavior is to fail closed: preserve the real `ID` data boundary, keep payload-owned text operators out of WordPress paragraphs, and mark the image dictionary for review-only raster handling.

## Change

- `PdfTextExtractor::readInlineImageDictionary()` now treats `ID` consumed as the value of a dangling name key as a malformed inline-image tail boundary when the parsed prefix already contains real image keys.
- The tokenizer handles both whitespace and comment boundaries after that `ID`, so image data starting after `ID\n` or `ID%...\n` remains image payload.
- `PdfImageRenderer::inlineImageDictionaryHasMalformedTailOperand()` now marks a missing value after already-seen inline-image keys as a malformed dictionary tail, keeping native raster preview review-only.
- `PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php` adds focused WordPress import cases for newline and comment-after-`ID` name-tail boundaries.
- `wordpress-pdf-inline-image-tokenizer-name-tail-currentbase.php` adds a local WordPress smoke showing only visible paragraphs are emitted and the malformed image preview fails closed.

## Red-First Evidence

Before the source edit, a synthetic searchable PDF with:

```text
BI /W 1 /H 1 /CS /G /BPC 8 /D [1 0] /BadTail /F /MalformedPreview ID
```

produced imported text:

```text
Before
Payload Noise
After
```

because the dictionary reader rejected the inline image and the content tokenizer consumed the payload text object after the early `EI` as visible text.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php
```

Result: `1 test files / 29 assertions / 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php
```

Result: `7 test files / 1902 assertions / 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-name-tail-currentbase.php
```

Result: smoke exits `0` with `visible_text_imported=true`, `name_tail_payload_excluded=true`, `name_tail_dictionary_operand_review_only=true`, `name_tail_preview_failed_closed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-name-tail-currentbase.php
```

Result: no syntax errors detected in each changed PHP file.

```bash
git diff --check -- lanes/markerpdf
```

Result: clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline-image tight `ID`, tight `EI`, NUL whitespace, comment-after-`ID` valid dictionaries, dot/scientific numeric malformed tails, Decode/DecodeParms malformed operands, color-space name operands, path/text-position boundaries, ActualText, XObject `Do`, or unsupported filter review-only cases. The bounded behavior is only top-level name-valued malformed dictionary tails where the trailing `ID` was previously consumed as a value token.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, image review plan, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, raster rendering engines, and exact upstream model benchmark parity remain intentionally out of scope under the markerPDF no-GPU directive.
