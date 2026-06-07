# Type3 CharProcs Dictionary Comment Boundary Current Base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260607T092347Z`
Base accepted HEAD: `a2dfaf1bb6d587edaf1feffea7751db293c974ea`

## Source Truth

The pinned markerPDF conversion path delegates searchable PDF text extraction
to PDFium/pdftext before layout, OCR, or model stages. Type3 `/CharProcs`
glyph streams are font programs, not standalone document text. PDF comments are
lexical whitespace, so a fake glyph map inside a leading comment before an
indirect `/CharProcs` dictionary object must not supply glyph widths or fallback
stream privacy decisions.

This no-GPU PHP slice keeps the boundary native: PDF object parsing, Type3
CharProc dictionary lookup, `d0`/`d1` width grouping, and stream fallback
exclusion only.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryCommentBoundaryCurrentBaseTest.php`

failed with:

- `Wide Block` instead of `WideBlock`, proving the comment-contained fake
  dictionary supplied narrow CharProc widths.
- `WIDE COMMENT PROGRAM LEAK` in fallback text, proving the real CharProc stream
  was not marked font-private when the real dictionary followed a comment.

## Implementation

`PdfTextExtractor::charProcsDictionaryBody()` now resolves indirect
`/CharProcs` dictionary objects through `singleDictionaryObjectBody()`. That
skips PDF whitespace/comments before the first real token and requires the
resolved object to be a single dictionary for the strict width/Unicode path.

The existing loose fallback-only reader remains unchanged for malformed
CharProcs glyph-tail and stream-dictionary exclusion slices.

## Focused Verification

Focused new slice:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryCommentBoundaryCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 0 failures`.

Adjacent Type3/font family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php`

Result: `55 test files, 578 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-comment-boundary-currentbase.php`

Result: exits 0 and emits `leading_comment_ignored_before_charprocs_dictionary=true`,
`comment_decoy_widths_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`fallback_content_preserved=true`,
`real_charproc_streams_excluded_from_fallback=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryCommentBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-comment-boundary-currentbase.php`

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type3 CharProc fallback exclusion, direct or
indirect glyph stream generation selection, indirect `/CharProcs` dictionary
generation selection, malformed indirect `/CharProcs` stream-object rejection,
direct dictionary tail rejection, duplicate top-level `/CharProcs` key
precedence, comment-split references, Type3 Encoding comment/generation
handling, nested/top-level dictionary parsing, filtered CharProc decoding,
metric operand validation, marked-content/graphics-state/path setup
boundaries, resource fallback exclusion, CMap/CIDSet width behavior, image
review, annotations, forms, xref repair, metadata, or supplied
table/equation handoffs.

The bounded behavior is only indirect `/CharProcs` dictionary objects whose
real dictionary is preceded by PDF comments containing fake dictionary tokens.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, comment skipper, single-dictionary parser, Type3 CharProc width parser,
fallback stream exclusion inventory, text extractor, and WordPress smoke path.
GPU/OCR/model execution, PDFium/pdftext runs, Python, and external PDF tools
remain intentionally out of scope.
