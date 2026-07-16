# markerPDF Type3 CharProcs FontMatrix boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T011132Z`

Base accepted HEAD: `24d8a02fe41aad85f9cde8b9bb0e256f650c48c8`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before assembling document blocks. At that boundary, PDF Type3 `/CharProcs`
are glyph programs, and `d0`/`d1` declare glyph widths in Type3 glyph space.
The Type3 `/FontMatrix` maps those glyph-space widths into text-space advance
before line grouping. The native no-GPU port must therefore normalize
CharProc-declared widths by the font matrix before deciding whether positioned
text belongs in the same WordPress paragraph.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'Thin Text')
```

That proved the native parser read `d0` width `500` from a Type3 font using
`/FontMatrix [0.002 0 0 0.002 0 0]` as 500 text-space units instead of the
normalized 1000-unit advance.

## Implementation

`PdfTextExtractor::type3CharProcWidths()` now multiplies each CharProc
declared horizontal width by a bounded top-level Type3 `FontMatrix` scale. The
helper reuses the existing PDF matrix parser and falls back to the accepted
default behavior when `/FontMatrix` is missing or malformed.

The focused fixture proves:

- `d0` width `500` with top-level `FontMatrix` scale `0.002` keeps `WideBlock`
  joined;
- `d1` width `125` with the same matrix preserves `Thin Text` spacing;
- nested resource FontMatrix decoys do not override the Type3 font dictionary
  matrix;
- Type3 CharProc payload text remains excluded from visible WordPress
  paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `13 test files, 730 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-fontmatrix-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`fontmatrix_charproc_widths_normalized=true`,
`wide_block_spacing_preserved=true`, `thin_text_spacing_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Additional local checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-fontmatrix-boundary-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, Type3 CharProc width parser, matrix parser,
stream decoder, and text-advance grouping path. No Python, PDFium, pypdfium2,
Surya, Texify, Torch, OCR, GPU/model execution, browser service, or external
PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, top-level
`/CharProcs` lookup, nested CharProcs dictionary parsing, Type3 Encoding
Differences, named/base Encoding color glyph widths, Type3 CMap/CIDSet
grouping, Type3 glyph-name Unicode recovery, Type0 CID widths, or xref/object
stream repair. The new boundary is specifically Type3 `/FontMatrix`
normalization for CharProc-declared glyph widths before WordPress text grouping,
with top-level FontMatrix lookup before nested resource decoys.
