# Font Width Advance Text-State Operand Count Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T183148Z`

Base accepted HEAD: `5cc85a3f48316145610b582134be336e1d3519d4`

## Source Truth

The no-GPU markerPDF lane stays at the native searchable-PDF parser boundary. PDF content-stream text-state operators such as `Tc`, `Tw`, and `Ts` take exactly one numeric operand, while the double-quote text-showing operator takes exactly `wordSpace charSpace string`. Extra leading operands are malformed content-stream input and must not become trailing text-state values that alter glyph advance or styled-span bboxes before WordPress import.

This slice keeps upstream OCR/model behavior out of scope and focuses only on native font advance and text-state parsing.

## Change

- `PdfTextExtractor` now requires exact operand counts for `Tc`, `Tw`, `Ts`, and the double-quote text-showing operator.
- Malformed extra `Tc` operands no longer add character spacing to styled-span advance bboxes.
- Malformed extra `Tw` operands remain ignored before word-spacing advance.
- Malformed extra `Ts` operands no longer raise styled-span bboxes.
- Malformed extra leading operands on the double-quote operator reject that text-showing operation instead of using the last three operands.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseTest.php
```

Result: `1 test file / 23 assertions / 3 failures`.

Failures showed extra `Tc` advancing bboxes to `[[0,0,30,12],[30,0,60,12]]`, extra `Ts` raising bboxes to `[[0,6,24,18],[24,6,48,18]]`, and malformed double-quote operands emitting `Tail`.

## Verification

Focused post-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseTest.php
```

Result: `1 selected test file / 44 assertions / 0 failures`.

Adjacent operand-count/text-state family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextShowingOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceTzOperandCountBoundaryCurrentBaseTest.php
```

Result: `5 selected test files / 124 assertions / 0 failures`.

Broader font-width current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvance*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php
```

Result: `20 selected test files / 1059 assertions / 0 failures`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-text-state-operand-count-currentbase.php
```

Result: exits `0` and reports `tw_extra_operands_ignored=true`, `tc_extra_operands_ignored=true`, `ts_extra_operands_ignored=true`, `quote_extra_leading_operands_rejected=true`, `font_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted `Tz` operand count handling, `Tj`/`TJ` text-showing operand counts, quote-operator tail operand behavior, overlarge `Tw`/quote spacing, `Tf` operand-tail boundaries, malformed width arrays, CID `/W`/`/W2`/`/DW`/`/DW2`, Type3 CharProc width fallback, or CMap source-width fallback. The bounded behavior is only fixed-arity text-state and double-quote text-showing operands before font-width advance and styled-span bbox grouping.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP content-stream tokenizer, font-width resolver, text-state parser, styled span bbox pipeline, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope.
