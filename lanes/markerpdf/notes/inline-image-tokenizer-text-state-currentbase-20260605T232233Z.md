# markerPDF inline image tokenizer text-state boundary current-base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T232233Z`

Base accepted HEAD: `fb46ebc3d2f23a2a2e13c04bc35eaf715f97f12d`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to parser-backed pdftext/PDFium before image/OCR/model stages. At that parser boundary, `BI ... ID ... EI` inline image bytes are raster payload, while valid content operators after the real `EI` terminator remain visible document text for WordPress import.

Preview-only inline images such as JBIG2 can contain delimiter-looking `EI` bytes before the native tokenizer can prove a complete raster payload. The fallback must close at the real image terminator when the following segment is valid content state plus text, not wait for a later stray `EI` and swallow the paragraph.

## Behavior

`PdfTextExtractor` now accepts bounded text-state operators between a preview-only inline image terminator and the next closed text object:

- `/F1 10 Tf`
- `14 TL`
- `1.5 Tc`
- `2 Tw`
- `95 Tz`
- `0 Tr`
- `1 Ts`

Before this slice, that valid state setup was rejected by the fallback segment validator, so the text object after it was treated as image payload and only the later post-stray paragraph survived. After the fix, both visible paragraphs are imported while `Text State Payload Noise` remains image-owned.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL closes preview-only fallback before text-state operators and text followed by stray EI operator (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Text State Stray',
  1 => 'Visible Text State Before Stray',
  2 => 'Visible After Text State Stray',
)
Actual: array (
  0 => 'Before Text State Stray',
  1 => 'Visible After Text State Stray',
)

1 test files, 388 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before text-state operators and text followed by stray EI operator
1 test files, 398 assertions, 0 failures
```

Adjacent inline-image parser/preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1018 assertions, 0 failures
```

Syntax and lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php

php -r '$p="lanes/markerpdf/lane-status.json"; $j=json_decode(file_get_contents($p), true); if (!is_array($j)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_text_state_stray_ei_text_preserved_after_safe_boundary=true`, visible text-state paragraphs, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` boundaries, immediate comments after `ID`, NUL whitespace, tight `EI` sample floors, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line text, q/cm/clipping path, XObject Do, color-state, pattern/shading/dash graphics-state, compatibility-section stray `EI`, decoded surplus sample floors, stream filters, image preview metadata, or OCR/model behavior.

The bounded behavior here is specifically text-state operators between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, preview-only image fallback scanner, text-state/content-segment validator, `PdfTextExtractor`, and WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
