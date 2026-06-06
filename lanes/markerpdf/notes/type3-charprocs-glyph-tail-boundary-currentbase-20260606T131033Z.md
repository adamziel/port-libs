# Type3 CharProcs Glyph-Entry Tail Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T131033Z`

Accepted base: `eecc865658e5cd10e8284e626f10d8b8a1b3a078`

## Source Truth

The markerPDF no-GPU lane maps searchable PDF import through native text extraction before WordPress block conversion. Upstream markerPDF delegates searchable PDF text to PDFium/pdftext, where Type3 `/CharProcs` are font glyph programs rather than page text. In the PHP fallback, a valid Type3 glyph stream may supply `d0`/`d1` metrics for text advance grouping, but malformed glyph dictionary entries must not drive those metrics, and glyph-program payload streams must remain private during stream fallback extraction.

## Red Check

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php`

failed with `BadPath` instead of `Bad Path`:

```text
FAIL rejects Type3 CharProcs glyph-entry tail operands before WordPress text grouping on current base
Expected: array (0 => 'Bad Path')
Actual:   array (0 => 'BadPath')
1 test files, 7 assertions, 1 failures
```

The fallback half covers the matching indirect malformed dictionary path, where glyph streams named by the bad dictionary still stay out of visible fallback text. The missing boundary was strict metric rejection for a valid glyph reference followed by a top-level non-name tail operand inside the `/CharProcs` dictionary.

## Patch

- `PdfTextExtractor::charProcObjectReferencesFromDictionary()` now rejects malformed glyph-reference tails for metric/Unicode lookup, returning no Type3 CharProc map when a glyph entry is followed by non-name top-level operands before the next glyph key.
- The fallback-only callers pass a lenient flag so malformed dictionaries can still mark referenced glyph streams as font-private and keep their payload text out of WordPress paragraphs.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-glyph-tail-currentbase.php` -> no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php` -> `1 test files, 12 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php` -> `50 test files, 524 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-glyph-tail-currentbase.php` -> emits `charprocs_glyph_entry_tail_rejected=true`, `fallback_widths_preserve_word_gap=true`, `charproc_payload_visible_text_excluded=true`, `fallback_content_preserved=true`, `malformed_dictionary_glyph_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, rendering only the paragraph `Bad Path`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type3 CharProc fallback-payload exclusion, direct `/CharProcs` dictionary tail rejection, duplicate top-level `/CharProcs` key precedence, indirect dictionary generation selection, stream-object dictionary fallback exclusion, top-level/nested dictionary parsing, comment-split references, encoding generation/comment parsing, FontMatrix/width precedence, D1 bbox operands, marked-content/graphics-state/path setup, image XObject review, resource fallback exclusion, CMap/CIDSet width behavior, xref repair, metadata, annotations, forms, OCR/model execution, or supplied table/equation handoffs. The new boundary is specifically malformed top-level tail operands after a glyph stream reference inside a `/CharProcs` dictionary entry.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, exact object lookup, Type3 CharProc metric path, fallback stream exclusion inventory, text grouping path, focused PHP runner, and WordPress smoke harness. GPU/OCR/model execution, Python/PDFium runtime, and external PDF tools remain intentionally out of scope.
