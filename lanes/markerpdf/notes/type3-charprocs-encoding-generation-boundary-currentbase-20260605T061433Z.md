# markerPDF Type3 CharProcs Encoding generation boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T061433Z`

Base accepted HEAD: `317f6a71ec66a8e7e201d966f82172fcb642f59d`

## Source Truth

Upstream markerPDF gets searchable-PDF text from pdftext/PDFium before model
handoff. At that native parser boundary, Type3 `/CharProcs` glyph streams
provide `d0` and `d1` widths, but those glyph streams are selected through the
Type3 font's `/Encoding` glyph-name map. PDF comments are lexical whitespace
inside indirect references, and same-number indirect objects with different
generations are distinct objects. Therefore `/Encoding 21 % ... 0 R` must
resolve generation `21 0`, not a stale `21 1` encoding dictionary, before
CharProc glyph mapping and WordPress paragraph grouping.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingGenerationBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'ABCD EFGHI', 1 => 'TUVW XYZ[')
```

That proved the current parser did not resolve the comment-split `/Encoding`
reference through exact generation before building the Type3 glyph-name to
CharProc width map.

## Implementation

`PdfTextExtractor` now resolves font `/Encoding` indirect objects through the
existing token-based indirect-reference reader and exact-generation object
lookup before:

- building simple font encoding fallback maps;
- building Type3 Encoding Differences glyph-name maps;
- parsing Type0/Type3 CMap encoding streams;
- checking Type3 CMap-encoded CIDSet eligibility.

The lower-level `/Name` reference reader now treats PDF comments as whitespace,
matching the earlier Type3 `/CharProcs` reference boundary while preserving
existing callers that only need the object number.

## Evidence

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingGenerationBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 8 assertions, 0 failures`.

Adjacent Type3/font run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result after fix: `21 test files, 796 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-encoding-generation-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`comment_split_encoding_reference_resolved=true`,
`exact_encoding_generation_selected=true`,
`stale_encoding_generation_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, token whitespace/comment skipper, font Encoding
parser, CMap parser, Type3 CharProc width parser, stream decoder, and
text-advance grouping path. No Python, PDFium, pypdfium2, Surya, Texify, Torch,
OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, top-level
`/CharProcs` lookup, nested CharProcs dictionary parsing, CharProcs
comment-reference handling, Type3 `/FontMatrix` normalization, full `wx/wy`
vector transformation, Type3 glyph-name Unicode recovery, image/subtype
CharProc boundaries, pre-metric paint rejection, inline-image CharProc
boundaries, Type0 predefined CMap behavior, xref/object-stream repair, OCR,
table recognition, annotations, forms, metadata, image filters, or security
preflight. The new boundary is only exact-generation and comment-as-whitespace
font `/Encoding` resolution before Type3 CharProc glyph mapping.
