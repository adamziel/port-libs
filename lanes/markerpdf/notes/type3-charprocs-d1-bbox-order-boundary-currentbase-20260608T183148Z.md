# Type3 CharProcs d1 bbox order boundary current-base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T183148Z`

Base accepted HEAD: `5cc85a3f48316145610b582134be336e1d3519d4`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text
layers before OCR/model fallback. In this no-GPU PHP lane, Type3 `/CharProcs`
are glyph programs: valid `d0`/`d1` widths may drive WordPress text grouping,
but malformed glyph program boundaries must fail closed to the font fallback
widths and must not leak CharProc payload text into Gutenberg paragraphs.

The `d1` operator declares `wx wy llx lly urx ury`. Existing coverage rejected
non-numeric bbox operands; this slice closes the remaining ordering boundary:
`llx` must not exceed `urx`, and `lly` must not exceed `ury`, before the `d1`
width vector is trusted.

No OCR, Surya/Texify/Torch, raster rendering, model execution, browser/PDF
engines, or external PDF tools are involved.

## Red-First Evidence

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects inverted Type3 CharProc d1 bbox order before WordPress text grouping on current base
Expected: ['Thin Text', 'Flip Gap', 'Yflip Gap']
Actual: ['Thin Text', 'FlipGap', 'YflipGap']
1 test files, 1 assertions, 1 failures
```

That proved inverted x/y bbox order still allowed a malformed `1000`-unit
Type3 `d1` advance to collapse WordPress word gaps that should have used the
declared `/Widths` fallback.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now validates the four
`d1` bbox operands after numeric parsing and before returning the Type3 width
vector. The change is limited to inverted bbox order. Valid positive and
zero-area bbox order remains structurally acceptable; existing non-numeric
operand, duplicate metric, negative width, and FontMatrix/vector boundaries
remain separate.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects inverted Type3 CharProc d1 bbox order before WordPress text grouping on current base
1 test files, 13 assertions, 0 failures
```

Adjacent Type3 metric run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNegativeWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 72 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-d1-bbox-order-currentbase.php
```

Result: exits `0`, renders `Thin Text`, `Flip Gap`, and `Yflip Gap`, and emits
`valid_d1_bbox_width_preserved=true`,
`inverted_x_bbox_order_rejected=true`,
`inverted_y_bbox_order_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-d1-bbox-order-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer,
object scanner, stream decoder, Type3 CharProc width parser, `/Widths`
fallback machinery, text advance grouping, TestRunner harness, and WordPress
smoke renderer. GPU/model execution, OCR, pypdfium/PDFium rendering,
Streamlit/FastAPI workers, live services, and external PDF tools remain
intentionally out of scope.

## Non-Overlap

This does not repeat accepted non-numeric `d1` bbox operand rejection, negative
width-vector rejection, duplicate metric rejection, operand-count validation,
FontMatrix/width-vector normalization, pre-metric path/text/graphics/marked
content setup, post-metric scope validation, Type3 SymbolEncoding CharProc
lookup, glyph-entry tail handling, fallback stream privacy, Type3 image review,
CMap/CIDSet Type3 spacing, xref repair, metadata, annotations, forms, image
filters, OCR/model execution, or supplied table/equation handoffs. The bounded
behavior is only inverted lower-left/upper-right `d1` bbox order before Type3
glyph widths drive WordPress text grouping.
