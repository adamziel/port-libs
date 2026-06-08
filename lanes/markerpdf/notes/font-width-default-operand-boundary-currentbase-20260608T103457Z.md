# markerpdf font-width default operand boundary current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T103457Z`
Base: `1931c96c286e44f278624dd3e62f6ff3b6cb363b`

## Behavior

Native searchable-PDF text extraction now rejects malformed CIDFont `/DW`
default-width operands that contain a trailing top-level operand, including
indirect helpers such as `/DW 7 0 R 1000`. Valid direct and indirect single
numeric `/DW` operands still resolve through the existing object-aware numeric
path. The boundary matters for WordPress paragraph grouping because a malformed
250-unit default width could previously create a false word gap in Type0 text
where no explicit `/W` rows exist.

No Python, OCR, models, raster rendering, action execution, or external PDF
tools are used.

## Red-first Evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthDefaultOperandBoundaryCurrentBaseTest.php`

failed with `1 test files, 12 assertions, 2 failures`; both tailed direct and
tailed indirect `/DW` cases emitted `Wide Block` instead of the expected
`WideBlock`.

## Verification

After the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthDefaultOperandBoundaryCurrentBaseTest.php`

passed with `1 test files, 32 assertions, 0 failures`.

Adjacent font-width/CMap width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidth*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontDescriptorMissingWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

passed with `17 test files, 1374 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-default-operand-boundary-currentbase.php --self-test`

exited `0` and recorded direct and indirect tailed `/DW` rejection with
`executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted `/W` array parsing, indirect `/W` helper tail
rejection, `/W2` vertical helper tail rejection, simple-font `/Widths`
boundaries, FontDescriptor `MissingWidth` tail rejection, Type3 FontMatrix
boundaries, or existing indirect `/DW` valid resolution. The new behavior is
specifically top-level tail rejection for CIDFont `/DW` default-width operands
before current text advance grouping.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF
dictionary operand scanner, object resolver, Type0/CIDFont width metrics, and
focused PHP test runner.
