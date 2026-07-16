# Type3 CharProcs Compatibility Operand Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T190728Z`

Base accepted HEAD: `038c671034a5d4c3c6fd5dda675d71a821040ce7`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to native PDF text
handling before OCR/model fallback. In this no-GPU PHP lane, Type3
`/CharProcs` are glyph programs: `d0`/`d1` metrics may drive text advance
grouping, but malformed setup must fail closed and glyph program payload text
must not become WordPress paragraphs.

PDF `BX`/`EX` compatibility sections are balanced content-stream operators.
`BX` itself has no operands. A queued operand before the outer `BX` is outside
the compatibility section and should not be discarded before trusting a later
Type3 metric.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityOperandBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'GoodWide', 1 => 'Bad GapZ')
Actual:   array (0 => 'GoodWide', 1 => 'BadGapZ')
```

That proved a malformed `(bad outer BX operand) BX ... EX 1000 0 d0` CharProc
discarded the bad outer operand and still erased the WordPress word gap.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now rejects queued
operands before an outer `BX` compatibility-section opener. Nested
compatibility content remains ignored while inside an already-open `BX`/`EX`
section, preserving accepted compatibility behavior.

The focused fixture proves:

- a valid Type3 CharProc still supplies the wide metric for `GoodWide`;
- a malformed outer-`BX` operand rejects the late `d0` metric;
- declared `/Widths` fallback preserves `Bad GapZ`;
- the compatibility operator payload, bad operand text, and CharProc text
  payload remain excluded from visible WordPress paragraphs.

## Evidence

Pre-fix focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1 assertions, 1 failures`.

Post-fix focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 11 assertions, 0 failures`.

Adjacent Type3 compatibility/scope run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectSetupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php
```

Result: `5 test files, 62 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-compatibility-operand-currentbase.php
```

Result: emitted Gutenberg paragraphs for `GoodWide` and `Bad GapZ` with
`valid_charproc_width_preserved=true`,
`outer_bx_operand_metric_rejected=true`,
`compatibility_operator_payload_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object
scanner, stream decoder, content tokenizer, Type3 CharProc width parser,
font-width grouping path, focused PHP tests, and WordPress smoke renderer.
Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser services, live providers, and external PDF tools were not run.

## Non-Overlap

This does not repeat accepted balanced `BX`/`EX` compatibility sections,
pre-metric text-object setup, post-metric text/scope validation, direct
`d0`/`d1` width handling, duplicate metric detection, Type3 FontMatrix/vector
width handling, direct/indirect `/CharProcs` dictionary boundaries, glyph-entry
tail rejection, duplicate glyph replacement, marked-content wrappers,
inline-image paint rejection, private resource fallback exclusion, CMap/CIDSet
Type3 width behavior, image review, xref repair, metadata, annotations, forms,
OCR/model execution, or supplied table/equation handoffs. The bounded behavior
is only queued operands before an outer Type3 CharProc `BX` compatibility
section opener.
