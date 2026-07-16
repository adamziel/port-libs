# markerpdf font-width advance DW2 operand boundary current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T121716Z`
Base: `a00e14f093dc188f213b61df223920efd39f90c6`

## Behavior

Native searchable-PDF text extraction now rejects malformed CIDFont `/DW2`
default vertical metric arrays unless they contain exactly two valid numeric
operands. Direct arrays such as `/DW2 [880 -250 /Tail]` and
`/DW2 [880 -250 1000]` no longer let a forged `-250` default vertical
displacement drive styled-span advance bboxes. Valid `/DW2 [880 -250]`
remains accepted.

This matters for WordPress PDF imports because vertical Type0/CIDFont text
uses `/DW2` displacement to estimate glyph extents and line grouping. A tailed
array could previously make vertical CJK-style text look artificially narrow in
review bboxes before native paragraph rendering.

No Python, OCR, models, raster rendering, action execution, or external PDF
tools are used.

## Red-first Evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceDw2OperandBoundaryCurrentBaseTest.php`

failed with `1 test files, 22 assertions, 2 failures`; both tailed direct
`/DW2` cases produced narrow `12/18` point vertical bboxes instead of the safe
default `48/72` point bboxes.

## Verification

After the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceDw2OperandBoundaryCurrentBaseTest.php`

passed with `1 test files, 33 assertions, 0 failures`.

Adjacent font-width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidth*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontDescriptorMissingWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php`

passed with `18 test files, 1025 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-dw2-operand-boundary-currentbase.php --self-test`

exited `0` and recorded name-tail and numeric-tail `/DW2` rejection with
`executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted CIDFont `/DW` tail rejection, CIDFont `/W` or
`/W2` array parsing, indirect `/W2` helper-tail rejection, simple-font
`/Widths`, FontDescriptor `MissingWidth`, Type3 FontMatrix, CMap source-width,
or text-state/operator advance slices. The bounded behavior is specifically
direct `/DW2` array cardinality and numeric validation before vertical
font-advance grouping.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
PDF object scanner, dictionary operand scanner, object resolver, Type0/CIDFont
width metric parser, styled text grouping, focused PHP test harness, and
WordPress smoke path. GPU/model OCR, Surya/Texify/Torch/model execution,
PDFium/pdftext parity runs, and external PDF tools remain intentionally out of
scope for this markerPDF no-GPU slice.
