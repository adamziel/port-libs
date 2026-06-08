# Font Width Advance Text-Showing Operand Count Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T152801Z`  
Session: `port-dev-markerpdf-font-width-advance-20260608T152801Z`  
Base accepted HEAD: `d0b4b38f59138165173e2184c28cc1c5296bac2f`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through pdftext/PDFium before Marker converts extracted text into blocks and spans. In the no-GPU PHP lane, the equivalent native boundary is PDF text-showing operator parsing before WordPress paragraph and styled-span import. PDF `Tj` takes exactly one string operand and `TJ` takes exactly one array operand; extra top-level operands must not let a later string/array become visible text or advance the font-width cursor.

## Behavior

`PdfTextExtractor::textShowingOperand()` now validates operand arity and kind for `Tj`, `TJ`, and the single-quote text-showing operator:

- `Tj` and `'` accept exactly one non-array text string operand.
- `TJ` accepts exactly one array text operand.
- malformed extra-operand `Tj` / `TJ` operators fail closed before text extraction, current advance updates, styled-span bboxes, and WordPress paragraph rendering.

The focused fixture uses valid `Lead`, then malformed `100 (Decoy) Tj` or `[(Bad)] [(Array)] TJ`, then a relative `72 0 Td (Safe) Tj`. Before the fix, the malformed operator consumed the final text operand, producing `LeadDecoySafe` or `LeadArraySafe` and poisoning the relative gap decision. After the fix, the bad operators emit no text and do not advance, so the relative move renders `Lead Safe`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextShowingOperandCountBoundaryCurrentBaseTest.php
FAIL rejects extra Tj operands before current font-width advance grouping on current base
Expected: ['Lead Safe']
Actual: ['LeadDecoySafe']
FAIL rejects extra TJ array operands before current font-width advance grouping on current base
Expected: ['Lead Safe']
Actual: ['LeadArraySafe']
1 test files, 2 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextShowingOperandCountBoundaryCurrentBaseTest.php
PASS rejects extra Tj operands before current font-width advance grouping on current base
PASS rejects extra TJ array operands before current font-width advance grouping on current base
1 test files, 24 assertions, 0 failures
```

Adjacent font-width/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTextShowingOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 1379 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-text-showing-operand-count-currentbase.php --self-test
```

The smoke exits `0` and reports `extra_tj_operand_rejected=true`, `extra_tj_operand_did_not_advance=true`, `extra_tj_array_operand_rejected=true`, `extra_tj_array_operand_did_not_advance=true`, `font_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted simple-font width arrays, CIDFont `/W`/`/W2`/`/DW`/`/DW2`, Type3 FontMatrix/CharProc widths, terminal `Tc`/`Tw`, relative `Td`, absolute `Tm`, text-rise, `Tz`, `TJ` numeric adjustment/backtracking, quote-operator spacing or quote operand-tail behavior, malformed CMap/filter handling, xref repair, metadata, annotations, forms, image/filter review, supplied tables/equations, or OCR/model/PDFium execution. It only covers extra top-level operands on `Tj` and `TJ` text-showing operators before native font-width advance grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP content-token parser, text-showing operator handling, font-width advance helpers, styled-span extraction, TestRunner harness, and WordPress smoke renderer. GPU/model OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, live services, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
