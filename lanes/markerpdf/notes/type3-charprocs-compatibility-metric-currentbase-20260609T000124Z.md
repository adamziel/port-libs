# Type3 CharProcs Compatibility Metric Boundary Current Base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260609T000124Z`

## Source Truth

The current markerPDF no-GPU lane owns searchable-PDF native extraction before
WordPress paragraph import. Type3 CharProcs are glyph programs: `d0`/`d1`
metric operators may drive text advance grouping, but a glyph program with
multiple metrics is malformed. PDF compatibility sections (`BX`/`EX`) are
allowed to carry ignored compatibility operators, but they must not hide a
second Type3 metric after the selected metric.

This maps the PDF parser boundary used by markerPDF's upstream PDF text path
without invoking Python, PDFium, pypdfium2, PIL, OCR, Surya/Texify/Torch,
model workers, or external PDF tools.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityMetricBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'GoodWide',
  1 => 'Compat Gap',
  2 => 'ThinText',
)
Actual: array (
  0 => 'GoodWide',
  1 => 'CompatGap',
  2 => 'Thin Text',
)

1 test files, 1 assertions, 1 failures
```

That proves the current base accepted duplicate `d0` and `d1` metrics hidden
inside a post-metric `BX`/`EX` compatibility section.

## Implementation

`PdfTextExtractor::type3CharProcHasAdditionalMetricOperator()` now checks for
`d0`/`d1` before skipping compatibility-section content. Unknown compatibility
operators remain ignored, but a second Type3 metric remains a duplicate metric
and forces fallback to the font's declared widths.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityMetricBoundaryCurrentBaseTest.php
```

Result: `1 test files, 11 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextObjectMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPostMetricTextScopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOpenScopeBoundaryCurrentBaseTest.php
```

Result: `10 test files, 120 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-compatibility-metric-currentbase.php
```

Result: exits `0`, emits WordPress paragraphs for `GoodWide`, `Compat Gap`,
and `ThinText`, and reports `compatibility_duplicate_d0_rejected=true`,
`compatibility_duplicate_d1_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, stream decoder, content tokenizer, Type3 CharProc width parser,
text-advance grouping path, focused PHP runner, and WordPress smoke path.
GPU/OCR/model execution and external PDF tooling remain intentionally out of
scope for markerPDF under the current lane directive.

## Non-Overlap

This does not repeat accepted Type3 direct/indirect CharProcs dictionary
selection, duplicate top-level `/CharProcs` keys, exact generation lookup,
duplicate metrics outside compatibility sections, duplicate metrics inside
text objects, pre-metric compatibility wrappers, compatibility operand
validation, graphics-state/marked-content/text-scope balance, inline-image
pre-metric rejection, FontMatrix vector widths, Type3 image review, CMap/font
encoding work, xref repair, metadata, annotations, forms, security preflight,
OCR/model work, or supplied table/equation handoffs. The bounded behavior is
only duplicate `d0`/`d1` Type3 metrics hidden inside post-metric `BX`/`EX`
compatibility sections.
