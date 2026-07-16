# Type3 CharProcs Encoding Comment Boundary Current Base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T232837Z`

Base accepted HEAD: `463e23b58e232021a39d809484cef165659f969d`

## Source Truth

The no-GPU markerPDF lane maps upstream searchable-PDF text extraction before Markdown/WordPress conversion. PDF comments are whitespace in PDF object syntax, including inside arrays. Type3 `/Encoding /Differences` arrays select glyph names that are then matched to Type3 `/CharProcs`; comment-only glyph-looking names must not shift the code-to-glyph map or cause CharProc payload streams to become visible text.

## Change

- `PdfTextExtractor::encodingDifferencesGlyphNames()` now tokenizes `/Differences` arrays with the existing PDF array parser instead of regex-scanning comments, strings, and nested values.
- `PdfTextExtractor::encodingDifferencesMap()` uses the same tokenized path for simple-font fallback maps, preserving the shared encoding behavior without adding a Type3-only duplicate parser.
- Added a focused Type3 fixture where comment names inside `/Differences` previously shifted `/W.comment` and `/T.thin` glyphs, producing `AecoEWide` and `TUiWXThi` instead of `WideBlock` and `Thin Text`.
- Added a WordPress smoke proving only the page text appears as Gutenberg paragraphs while Type3 CharProc payload text and comment glyph names are excluded.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingCommentBoundaryCurrentBaseTest.php`

Result: failed with `Expected: ['WideBlock', 'Thin Text']`, actual `['AecoEWide', 'TUiWXThi']`; `1 test files / 1 assertions / 1 failures`.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingCommentBoundaryCurrentBaseTest.php`

Result: `1 test files / 11 assertions / 0 failures`.

Adjacent Type3 and encoding sweep:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProcs*Test.php' -o -name 'PdfFontType3*Test.php' -o -name 'PdfFontSimpleType3*Test.php' -o -name 'PdfFontCMapCidType3*Test.php' -o -name 'PdfFontCidType3*Test.php' \) -printf '%p\n' | sort) lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php`

Result: `45 test files / 403 assertions / 0 failures`.

Syntax and smoke:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

`php -l lanes/markerpdf/tests/PdfFontType3CharProcsEncodingCommentBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-encoding-comment-boundary-currentbase.php`

All reported no syntax errors.

`php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-encoding-comment-boundary-currentbase.php`

Result: emitted `encoding_comment_names_ignored=true`, `wide_block_spacing_preserved=true`, `thin_text_spacing_preserved=true`, `comment_glyph_names_visible_text_excluded=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

`PdfTextExtractorTest.php` note: the accepted-HEAD source copied into a temporary repo layout still has two unrelated `/UseCMap` failures in this worktree (`Import Blocks` and cycle guard cases), so that file was not used as a green gate for this Type3 slice.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type3 CharProc comment-split indirect references, exact-generation `/CharProcs` selection, Encoding generation selection, nested/top-level CharProcs dictionaries, Type3 FontMatrix width-vector behavior, pre-metric path/text-state/graphics-state setup, pattern/color/ExtGState resource fallback exclusion, inline-image guards, CMap `/UseCMap`, xref repair, metadata, outlines, annotations, forms, images, or supplied table/equation handoffs. The bounded behavior is only token-aware parsing of PDF comments inside Type3 `/Encoding /Differences` arrays before CharProc glyph lookup.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF array tokenizer, Type3 encoding parser, CharProc width parser, text extraction pipeline, and WordPress smoke path. GPU/OCR/model execution, pypdfium, Python workers, and external PDF tools remain intentionally out of scope.
