# Inline Image Tokenizer Indirect Property Boundary Current Base

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction on parser-backed content streams before any image/OCR/model fallback. At that boundary, `BI ... ID ... EI` bytes remain inline image payload, while valid marked-content operators after the real `EI` terminator must remain visible text for WordPress import.

## Behavior

Preview-only inline images such as JBIG2 can contain delimiter-looking `EI` bytes before the tokenizer can prove the image payload is complete. The existing fallback records an earlier candidate terminator, then closes at that earlier boundary only when the segment before a later stray `EI` is recognizable PDF content.

This slice extends that segment validator to accept bounded indirect marked-content property operands before following text:

- `/Span 6 0 R BDC` before `BT ... Tj ET EMC`
- `/Span 6 0 R DP` before `BT ... Tj ET`

The operand safety is shared with the existing Type3 CharProc indirect-property handling: object number and generation must be decimal digits, object number must be positive, and the reference operator must be `R`.

Before the fix, the red-first run swallowed the visible marked-content text and preserved only the text before the inline image plus the text after the later stray `EI`:

```text
Expected: Before Indirect BDC Boundary, Visible Indirect BDC Text, After Indirect BDC Boundary
Actual:   Before Indirect BDC Boundary, After Indirect BDC Boundary
```

After the fix, the tokenizer closes at the real inline-image terminator and preserves both indirect-property text paragraphs while keeping inline image payload noise review-only.

## Verification

Red-first probe:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerIndirectPropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 assertions, 2 failures
```

Fixed focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerIndirectPropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before indirect BDC property text followed by stray EI operator
PASS closes preview-only fallback before indirect DP property text followed by stray EI operator
1 test files, 24 assertions, 0 failures
```

Adjacent shared-helper check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerIndirectPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectPropertyBoundaryCurrentBaseTest.php
2 test files, 37 assertions, 0 failures
```

Inline-image tokenizer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizer*CurrentBaseTest.php
7 test files, 876 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-indirect-property-currentbase.php
```

The smoke exits 0 and emits `bdc_visible_text_imported=true`, `bdc_inline_payload_excluded=true`, `dp_visible_text_imported=true`, `dp_inline_payload_excluded=true`, and no Python/models or external PDF tools executed.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample floors, comment/NUL boundaries, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, direct-dictionary or named-property ActualText, TJ/quote fallback, post-terminator comments, q/Q/cm/clipping/path/color/dash/text-state/shading/operator boundaries, Type3 metric fallbacks, image-mask dictionary tails, CMap source-width fallback, OCR/model behavior, or raster image decoding. The new boundary is specifically indirect marked-content property references between a preview-only inline image terminator and visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, preview-only image fallback scanner, marked-content operand validator, and WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, and model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
