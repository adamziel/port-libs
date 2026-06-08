# markerPDF font width composed advance boundary

Session: `port-dev-markerpdf-font-width-advance-20260608T125951Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260608T125951Z`
Base accepted HEAD: `e65d6824c4b52805d383debe5763a0de4e4f464d`

## Scope

This isolated no-GPU markerPDF slice maps a searchable-PDF font-width boundary in the native PHP text extractor. Upstream markerPDF delegates searchable-PDF text geometry to parser-backed `pdftext` data before converting spans into Markdown/WordPress blocks, so the PHP fallback must keep text-advance math bounded before making paragraph gap decisions.

The new behavior covers composed horizontal advance overflow: each `/Widths` entry and `Tf` operand can be individually finite and accepted, while their product across a long text operand creates an absurd current text cursor. `PdfTextExtractor` now bounds the composed horizontal advance and falls back to the existing simple text advance estimate for that text operand. This keeps WordPress paragraph gaps and styled-span bboxes aligned without changing normal `/Widths`, `/W`, `/DW`, Type3, `TJ`, `Td`, `Tm`, or text-state behavior.

## Red-First Evidence

Before the extractor change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceComposedMetricBoundaryCurrentBaseTest.php`

failed after 1 assertion because the imported line was:

`AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWord`

instead of the expected:

`AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA Word`

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceComposedMetricBoundaryCurrentBaseTest.php`

Result: `1 test files, 11 assertions, 0 failures`.

Adjacent font-width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceComposedMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthDefaultOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthRangeOperandAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceScalarOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceIndirectScalarSlotBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceTfOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceQuoteOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvancePositionOperandBoundaryCurrentBaseTest.php`

Result: `11 test files, 885 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-composed-advance-currentbase.php`

Result: exits `0`; emits bounded local styled span bboxes `[0, 0, 600, 12]` and `[648, 0, 756, 12]`, preserves the paragraph gap before `WordPress`, excludes font metadata from visible text, and records `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP pass cases: `+1`
- Focused assertions: `+11`
- WordPress smoke scenarios: `+1`
- `phpPass`: `3104 -> 3105`
- `wordpressScenarios`: `2558 -> 2559`
- Mapped upstream denominator: unchanged; this stays inside the existing native font-width advance behavior cluster.

## Non-Overlap

This does not repeat accepted default `/DW` operand guards, indirect width array tails, simple-font range operand tails, scalar `/W` operand tails, `Tf` operand tails, quote-operator operand tails, oversized `Tm`/`Td` operand rejection, text-state spacing advance, Type3 FontMatrix advances, vertical `/W2`, CIDSet/default-width, page resource ToUnicode/width boundaries, or object-stream font-resource slices. The new boundary is specifically the composed product of many individually accepted font-width metrics before same-line paragraph gap and styled-bbox decisions.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, content token parser, simple-font width parser, text operand source extraction, current text advance estimator, styled-span bbox path, and WordPress smoke renderer. GPU/OCR/model execution, Surya/Texify/Torch workers, external PDF tools, raster rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue non-overlapping native searchable-PDF behavior around CMaps, font resources, xref repair, stream filters, annotations/forms, page geometry, image/filter review metadata, or supplied-boundary table/equation handoffs.
