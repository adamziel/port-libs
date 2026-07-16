# markerPDF inline image tokenizer scientific numeric boundary current-base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T230606Z`

Base accepted HEAD: `ca789a5b6d3e33e7c4378e92f189c08d2e32e040`

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction on a parser-backed path before image/OCR/model fallback. Inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload, while valid content operators after the real `EI` terminator remain visible document text for WordPress import.

Some producer-repaired PDFs use exponent-form numbers in content streams. The native tokenizer must still recognize those numeric operands when validating same-line graphics prefixes after a preview-only inline image terminator, so it does not scan ahead to a later stray `EI` and swallow searchable text.

## Behavior

`PdfTextExtractor::numericOperand()` now accepts exponent-form numeric tokens such as `1e0`, `6e1`, and `6.4e2`.

The focused fixture covers preview-only JBIG2 inline image fallback followed on the same line by:

- `1e0 0 0 1e0 24 0 cm BT ... ET EI`
- `6e1 6.4e2 2.6e2 7e1 re W n BT ... ET EI`

After the fix, the tokenizer closes the inline image before those valid graphics prefixes, preserves the CM and clipping text, and keeps inline payload bytes plus `JBIG2Decode` out of extracted WordPress paragraphs.

## Red First

A probe fixture on the accepted base showed the same-line exponent `cm` and clipping prefixes were treated as invalid fallback-boundary content. The extracted lines kept only the surrounding paragraphs and dropped `Visible Exponent CM Prefix`, `Visible After Exponent CM Prefix`, `Visible Exponent Clip Prefix`, and `Visible After Exponent Clip Prefix`.

The already accepted tokenizer test file passed before this new case was added:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result before this slice: `1 test files, 672 assertions, 0 failures`.

## Verification

Focused tokenizer test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 683 assertions, 0 failures`.

Adjacent inline-image parser/preview family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: `12 test files, 2297 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

Result: exit 0 with `preview_only_scientific_numeric_prefix_stray_ei_text_preserved_after_safe_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and lane hygiene:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

`php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

Result: no syntax errors detected.

`php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Result: `lane-status json ok`.

`git diff --check -- lanes/markerpdf`

Result: clean.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample-floor behavior, JBIG2/CCITT/DCT/JPX filter boundaries, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line text, q/cm/clipping path without exponent-form operands, XObject Do, color/pattern/shading/dash/text-state/compatibility/external-close fallback operators, Type3 metric operators, Decode exact-generation image preview behavior, or OCR/model behavior.

The bounded behavior here is exponent-form numeric operands in same-line `cm` and clipping-path graphics prefixes between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, numeric operand parser, `PdfTextExtractor`, and WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
