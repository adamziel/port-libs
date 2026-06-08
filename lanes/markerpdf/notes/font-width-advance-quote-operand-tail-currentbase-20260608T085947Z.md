# Font Width Advance Quote Operand Tail Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T085947Z`  
Session: `port-dev-markerpdf-font-width-advance-20260608T085947Z`  
Base accepted HEAD: `c9ab75c0ee03464f8aeb5e5a12fffd3aa5904d85`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through pdftext/PDFium before Marker converts extracted text into blocks and spans. In the no-GPU PHP lane, the equivalent native boundary is PDF text-showing operator parsing before WordPress paragraph and styled-span import. PDF `"` is the double-quote text-showing operator with operand shape `aw ac string`; tailed operands after the string must not let an earlier string become visible text or poison font-width advance state.

## Behavior

`PdfTextExtractor` now accepts a `"` text operand only when the final operand is text and the two immediately preceding operands are numeric `aw`/`ac` slots. Valid overlarge numeric `aw`/`ac` values still fail closed through the existing finite metric guard while allowing the final string to import with the previous safe spacing.

The focused fixture uses:

- prior safe `30 Tw` and `4 Tc` text state;
- valid visible `Lead`;
- malformed `100 100 (Decoy) 24 "` where `(Decoy)` is not the final operand;
- safe following `(A B)` text on the next line.

Before the fix, the decoy string was imported and concatenated into the following line:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php
FAIL rejects tailed quote-operator string operands before current advance grouping on current base
Expected: ['Lead', 'A B']
Actual: ['Lead', 'DecoyA B']
```

After the fix, the malformed quote operator contributes no visible decoy string and does not replace the prior safe spacing:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php
PASS rejects tailed quote-operator string operands before current advance grouping on current base
1 test files, 13 assertions, 0 failures
```

Adjacent verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 1312 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-quote-operand-tail-currentbase.php --self-test
```

The smoke exits `0` and reports `tailed_quote_string_rejected=true`, `quote_spacing_tail_did_not_poison_advance=true`, `poisoned_quote_spacing_bbox_excluded=true`, `font_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted simple-font width arrays, CIDFont `/W`/`W2`, Type3 FontMatrix/CharProc widths, terminal `Tc`/`Tw`, relative `Td`, absolute `Tm`, text-rise, `Tz`, huge `Tf`, `TJ` backtracking/overflow, overlarge quote spacing, malformed CMap/filter behavior, xref repair, metadata, annotations, forms, image/filter review, supplied tables/equations, or OCR/model/PDFium execution. It only covers malformed tail operands after the string operand of the `"` text-showing operator before native text advance grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP content-token parser, text-state operator handling, font-width advance helpers, styled-span extraction, TestRunner harness, and WordPress smoke renderer. GPU/model OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, live services, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
