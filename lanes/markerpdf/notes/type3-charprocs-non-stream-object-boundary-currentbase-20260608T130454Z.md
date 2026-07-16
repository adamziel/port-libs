# Type3 CharProcs Non-Stream Object Boundary

- Slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T130454Z`
- Base accepted HEAD: `a6a533bea4c4d3662d74d680c80c05f4d7dc212d`
- Scope: native no-GPU markerPDF searchable-PDF font parsing.

## Source Truth

Upstream markerPDF routes searchable PDF text through PDFium/pdftext before
assembling marker blocks. At that native PDF boundary, Type3 `/CharProcs`
entries are glyph stream programs. A plain indirect object body containing
`1000 0 d0` is not a valid CharProc stream and must not override `/Widths` or
`/FontDescriptor /MissingWidth` during WordPress text grouping.

## Red Check

Before the source edit, the focused fixture failed because
`PdfTextExtractor::type3CharProcDeclaredWidthVector()` parsed a non-stream
object body as CharProc content:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNonStreamObjectBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL requires Type3 CharProc entries to be stream objects before WordPress width grouping on current base
Expected: array (
  0 => 'GoodWide',
  1 => 'Plain Gap',
)
Actual: array (
  0 => 'GoodWide',
  1 => 'PlainGap',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now requires an actual
decoded CharProc stream. Non-stream CharProc references fail closed to the
existing Type3 `/Widths` and FontDescriptor fallback metric path, while valid
CharProc streams still provide `d0`/`d1` widths.

The focused fixture proves:

- a valid Type3 CharProc stream keeps `GoodWide` joined;
- a malformed non-stream CharProc object cannot supply a wide `d0` width;
- fallback widths preserve `Plain Gap`;
- CharProc program text remains excluded from visible WordPress paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNonStreamObjectBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php
```

Result: `67 test files, 801 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-non-stream-object-currentbase.php --self-test
```

Result: exits 0 and emits Gutenberg paragraphs for `GoodWide` and `Plain Gap`
with `valid_stream_charproc_width_preserved=true`,
`non_stream_charproc_width_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsNonStreamObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-non-stream-object-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all pass; `git diff --check -- lanes/markerpdf` produced no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, exact-generation object lookup, stream dictionary decoder, Type3
CharProc metric parser, fallback width handling, and WordPress smoke path. No
Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser service, live provider, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted Type3 fallback-payload exclusion, top-level
`/CharProcs` lookup, indirect/direct dictionary tail rejection, array-wrapped
CharProcs rejection, duplicate glyph/metric handling, stream filter
fail-closed behavior, inline-image paint rejection, text-object setup,
FontMatrix/width-vector normalization, escaped glyph names, Type3 CMap/CIDSet
spacing, resource fallback exclusion, image review, metadata, annotations,
forms, xref repair, or supplied-boundary table/equation behavior. The boundary
is only non-stream CharProc object rejection before Type3 width grouping.
