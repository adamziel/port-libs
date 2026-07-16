# markerPDF Type3 CharProcs pre-metric paint boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T035612Z`

Base accepted HEAD: `24c4644c214503440645874cb6dbfb7ef8927022`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before assembling document blocks. At that PDF parser boundary, Type3
`/CharProcs` are glyph programs, and `d0`/`d1` glyph metric operators drive text
advance decisions while CharProc paint/text payloads remain hidden from visible
WordPress paragraphs.

Accepted markerPDF Type3 notes already cover direct `d0`/`d1` widths,
same-number stream generation, indirect `/CharProcs` dictionaries, nested
dictionary boundaries, stream filters, Type3 `FontMatrix` scaling, named/base
encoding color glyphs, Type3 ToUnicode/CMap/CIDSet width behavior, and
CharProc fallback-payload exclusion. This slice narrows the remaining boundary:
metrics remain valid after harmless graphics-state/color setup operators, but a
`d0`/`d1` that appears after text/paint operators is rejected so malformed glyph
programs cannot supply a late width that erases WordPress word gaps.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Late Gap',
)
Actual: array (
  0 => 'WideBlock',
  1 => 'LateGap',
)

1 test files, 1 assertions, 1 failures
```

That proved the native Type3 width parser accepted a `d0` operator after a
pre-metric `BT ... Tj ... ET` paint/text sequence and used that invalid late
width to join `LateGap`.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidth()` now rejects a CharProc width
when any non-setup operator appears before `d0` or `d1`. A small helper keeps
accepted pre-metric graphics-state and color setup operators (`q`, `Q`, `cm`,
line-state operators, `gs`, and color operators such as `rg`, `cs`, and `scn`)
available for Type3 color glyph fixtures while rejecting pre-metric text,
XObject invocation, path construction, and paint operators.

The focused fixture proves:

- valid initial `d0` width keeps `WideBlock` joined;
- a late `d0` after pre-metric text painting is rejected;
- `/FontDescriptor /MissingWidth 250` is used for the rejected glyph metrics,
  preserving `Late Gap`;
- CharProc text payloads remain excluded from visible Gutenberg paragraphs.

## Evidence

Focused test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

Adjacent Type3/font sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `19 test files, 783 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-initial-operator-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Late Gap`, with
`valid_initial_metric_width_preserved=true`,
`post_paint_metric_rejected=true`, `missing_width_fallback_used=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Changed PHP lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-initial-operator-boundary-currentbase.php
```

Result: no syntax errors.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, Type3 CharProc dictionary parser, stream
decoder, content tokenizer, FontDescriptor fallback width handling, text
advance grouping path, and WordPress smoke path. No Python, PDFium,
pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser service, or
external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect `/CharProcs` dictionary exact-generation selection, top-level
`/CharProcs` lookup, nested CharProcs dictionary parsing, Type3 stream-filter
fail-closed behavior, Type3 `FontMatrix` normalization, named/base encoding
color glyph widths, Type3 CMap/CIDSet grouping, Type3 glyph-name Unicode
recovery, Type0 CID widths, image/filter boundaries, or xref/object-stream
repair. The new boundary is specifically post-paint/post-text Type3 CharProc
metric rejection before WordPress text grouping.

Root harness: not run - isolated micro-slice.
