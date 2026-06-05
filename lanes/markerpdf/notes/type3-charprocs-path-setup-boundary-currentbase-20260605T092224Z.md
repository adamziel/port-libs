# markerPDF Type3 CharProcs path-setup boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T092224Z`

Base accepted HEAD: `8018c2edef5162f55a780010aec2655e6598b40f`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before model handoff. At this native parser boundary, Type3 `/CharProcs` are
glyph programs whose `d0`/`d1` operators provide glyph widths before text
advance grouping. The PDF reference defines Type3 CharProcs as PDF page marking
operator streams and identifies `d0`/`d1` as the Type3 width/cache-device
operators. This port already accepted non-painting setup wrappers before the
metric operator; this slice extends the same bounded tolerance to non-painting
path construction, clipping, and end-path operators while still failing closed
when painting, text, image, or XObject operators occur before metrics.

Reference: Adobe PDF Reference 1.0, Type 3 fonts and Type 3 font operators:
https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.0.pdf

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'ThinText')
```

That proved the native parser rejected CharProc metric operators after
non-painting path setup and then fell back to stale `/Widths` values in both
directions: false `Wide Block` spacing for `d0` and false `ThinText` joining
for `d1`.

## Implementation

`PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now treats PDF
path construction operators (`m`, `l`, `c`, `v`, `y`, `h`, `re`) plus clipping
and end-path operators (`W`, `W*`, `n`) as non-painting setup before a Type3
metric operator.

The focused fixture proves:

- path construction before `d0` still lets the CharProc width override a stale
  narrow `/Widths` fallback;
- rectangle path setup, clipping, and `n` before `d1` still lets the CharProc
  width override a stale wide `/Widths` fallback;
- accepted fail-closed behavior for pre-metric painting remains covered by the
  adjacent Type3 family;
- CharProc payload text remains excluded from visible WordPress paragraphs.

## Evidence

Focused red-first/green command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 9 assertions, 0 failures`.

Adjacent Type3/font run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `26 test files, 838 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-path-setup-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`path_setup_charproc_widths_preserved=true`,
`wide_block_spacing_preserved=true`, `thin_text_spacing_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-path-setup-boundary-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, exact-generation object lookup, Type3 CharProc width parser,
content-tokenizer, stream decoder, and text-advance grouping path. No Python,
PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser
service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, comment-split
CharProc references, top-level `/CharProcs` lookup, nested CharProcs dictionary
parsing, Type3 Encoding Differences, named/base Encoding color glyph widths,
Type3 CMap/CIDSet grouping, Type3 glyph-name Unicode recovery, Type3
`/FontMatrix` normalization, `wx/wy` vector transformation, marked-content
wrappers, inline-image paint rejection, image/subtype CharProc boundaries,
pre-metric painting rejection, or xref/object-stream repair. The new boundary
is only non-painting path setup before Type3 `d0`/`d1` metrics.
