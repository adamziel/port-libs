# Type3 CharProcs Pattern Color Operand Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T221352Z`

Base accepted HEAD: `8939d9ec1b75b1ccc78dcd11b00b99d8e8fa44a9`

## Upstream Boundary

Upstream markerPDF delegates searchable PDF glyph text extraction to PDF parser
behavior before layout/OCR/model stages. For native no-GPU PHP extraction,
Type3 `/CharProcs` remain glyph programs: `d0`/`d1` width metrics may drive
WordPress text grouping, but malformed pre-metric drawing setup should not make
bad glyph metrics authoritative.

PDF color operators `SCN`/`scn` can carry pattern color operands where numeric
components precede a pattern name. The previous native validator accepted any
mix of numeric operands and PDF names in any order, so a malformed CharProc like
`/Pattern cs /GlyphPattern 0.25 scn 1000 0 d0` was trusted as a wide glyph and
collapsed a real `Bad Join` word gap into `BadJoin`.

## Implementation

- `PdfTextExtractor::type3CharProcColorOperandsAreSafe()` now allows finite
  numeric operands and at most one PDF name.
- If a PDF name is present, it must be the final color operand. Later numeric
  components or a second name reject the CharProc metric and fall back to
  existing finite `/Widths` or `/MissingWidth` behavior.
- Existing valid Type3 color/pattern cases remain accepted: numeric-only color
  operands, name-only uncolored pattern operands, and numeric components
  followed by a pattern name.

## Evidence

Red focused run after adding the fixture, before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects Type3 CharProc pattern color names before numeric components on current base
Expected: ['GoodPath', 'Bad Join']
Actual: ['GoodPath', 'BadJoin']
1 test files, 1 assertions, 1 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects Type3 CharProc pattern color names before numeric components on current base
1 test files, 10 assertions, 0 failures
```

Adjacent Type3/font run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
42 test files, 406 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-pattern-color-operand-currentbase.php
```

The smoke emits `valid_pattern_color_widths_resolved=true`,
`malformed_pattern_color_widths_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`pattern_resource_name_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`, followed by Gutenberg paragraphs
`GoodPath` and `Bad Join`.

## Non-Overlap

This does not repeat accepted Type3 direct `d0`/`d1` width handling, CharProc
fallback exclusion, exact-generation lookup, indirect `/CharProcs` dictionary
selection, stream-filter fail-closed behavior, FontMatrix normalization,
resource fallback, valid pattern resource exclusion, Type3 color glyph
numeric-only widths, marked-content validation, inline-image rejection, graphics
state balance, CMap/CIDSet grouping, xref repair, metadata, annotations, forms,
images, OCR/model execution, or supplied table/equation handoffs. The bounded
behavior is only ordering validation for mixed numeric plus name `SCN`/`scn`
color operands before Type3 metric operators.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object
scanner, content-token parser, Type3 CharProc width parser, text advance
grouping, and WordPress smoke path. GPU/OCR/model execution, pypdfium, PIL,
Python workers, and external PDF tools remain intentionally out of scope.
