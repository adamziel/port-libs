# markerPDF Type3 CharProcs comment-reference boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T054012Z`

Base accepted HEAD: `c243ffed38bf9ac26b8935ac6b66c7d9fd11f2ac`

## Source Truth

Upstream markerPDF gets searchable-PDF text from pdftext/PDFium before model
handoff. At that native parser boundary, PDF comments are lexical whitespace,
including inside indirect references. Type3 `/CharProcs` dictionaries are glyph
program maps, so comment-split `/CharProcs 21 % ... 0 R` and glyph references
such as `/W 3 % ... 0 R` must still resolve before text grouping. CharProc
payload text remains glyph program content and must not become WordPress
paragraph text.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'Thin Text')
```

That proved the current parser treated comments inside Type3 indirect
references as a hard boundary, lost the `d0` width, and introduced a false
WordPress word gap.

## Implementation

`PdfTextExtractor` now reads Type3 `/CharProcs` dictionary references and glyph
stream references with a token-based indirect-reference helper that reuses the
existing PDF whitespace/comment skipper. The change is scoped to Type3
CharProc resolution and leaves broader indirect-reference parsing untouched.

The focused fixture proves:

- a top-level Type3 `/CharProcs` reference split by a PDF comment resolves;
- glyph CharProc stream references split by PDF comments resolve;
- `d0` and `d1` widths still drive line grouping;
- CharProc payload text and comment text stay out of visible WordPress
  paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 8 assertions, 0 failures`.

Adjacent Type3/font run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result after fix: `20 test files, 788 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-comment-reference-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`comment_split_charprocs_reference_resolved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, token whitespace/comment skipper, Type3
CharProc width parser, stream decoder, and text-advance grouping path. No
Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, top-level
`/CharProcs` lookup, nested CharProcs dictionary parsing, Type3 Encoding
Differences, named/base Encoding color glyph widths, Type3 CMap/CIDSet
grouping, Type3 glyph-name Unicode recovery, Type3 `/FontMatrix`
normalization, `wx/wy` vector transformation, image/subtype CharProc
boundaries, pre-metric paint rejection, or xref/object-stream repair. The new
boundary is only comment-as-whitespace token handling inside Type3 CharProc
indirect references before WordPress text grouping.
