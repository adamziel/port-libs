# markerPDF Type3 CharProcs operand-count boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T103408Z`

Base accepted HEAD: `05e3db7e0ccb37bb704fa63dae3d9c01b791d492`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR/model handoff. At this native parser boundary, Type3 `/CharProcs`
are glyph programs. Their `d0` and `d1` operators provide glyph metrics for
text advance grouping, but the glyph program payload itself is not visible page
text.

The relevant PDF behavior is operator arity: Type3 `d0` takes exactly `wx wy`,
and Type3 `d1` takes exactly `wx wy llx lly urx ury`. Malformed CharProc
programs with extra numeric operands before `d0` or `d1` should not let the
native fallback parser pick trailing operands and override safe `/Widths` or
`/MissingWidth` fallback metrics.

## Red check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php
```

failed with:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects Type3 CharProc d0 d1 metrics with extra operands before WordPress grouping on current base
Expected: ['WideBlock', 'Late Gap', 'ThinJoin']
Actual:   ['WideBlock', 'LateGap', 'Thin Join']
1 test files, 1 assertions, 1 failures
```

That proved `PdfTextExtractor` accepted `999 1000 0 d0` by taking the trailing
`1000 0` and accepted `999 250 0 0 0 250 700 d1` by taking the trailing six
operands. Those malformed metrics erased a required WordPress word gap in one
case and invented a false word gap in the other.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now requires exact
operand counts for Type3 metric operators after allowed pre-metric setup:

- `d0` must see exactly two operands and uses operands `0` and `1`;
- `d1` must see exactly six operands and uses operands `0` and `1`;
- valid sibling CharProc metrics remain authoritative over stale `/Widths`;
- malformed extra-operand metrics fail closed to existing fallback widths;
- CharProc text payloads remain excluded from visible WordPress paragraphs.

## Verification

Focused green:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php
```

Result: `1 test files, 8 assertions, 0 failures`.

Scoped Type3/font family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
```

Result: `28 test files, 238 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-operand-count-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock`, `Late Gap`, and
`ThinJoin`, with `valid_sibling_charproc_width_preserved=true`,
`malformed_d0_operand_count_rejected=true`,
`malformed_d1_operand_count_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-operand-count-boundary-currentbase.php
```

Result: all passed.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
stream decoder, content tokenizer, Type3 `/CharProcs` dictionary resolver,
FontMatrix/width parsing, simple-font width fallback, and WordPress smoke
renderer. No Python, PDFium, pypdfium2, Poppler, Ghostscript, OCR, Surya,
Texify, Torch, GPU/model execution, browser service, live provider, or
external PDF tool was run.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc
fallback exclusion, exact stream generation selection, indirect CharProcs
dictionary generation, comment-split references, top-level/nested dictionary
guards, Type3 Encoding differences, private glyph fallback, named/base
Encoding color glyph widths, Type3 CMap/CIDSet grouping, Type3 FontMatrix
normalization, `wx/wy` vector transformation, marked-content wrappers,
path-setup wrappers, BX/EX compatibility sections, inline-image paint
rejection, image/resource subtype decoys, pre-metric painting rejection, or
xref/object-stream repair. The new behavior is only exact `d0`/`d1` operand
arity before using Type3 CharProc metrics for WordPress text grouping.

Root harness: not run - isolated micro-slice.
