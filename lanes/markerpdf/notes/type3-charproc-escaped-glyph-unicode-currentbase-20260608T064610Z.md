# Type 3 CharProc Escaped Glyph Names - Current Base

- Slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T064610Z`
- Accepted base: `2963610daf96767276a1776d5d1df7e0ba0844de`
- Scope: native no-GPU markerPDF Type 3 font parsing. This does not launch OCR, Surya/Texify/Torch, raster rendering, model workers, or external PDF tools.

## Behavior

PDF names can encode bytes with `#xx` escapes. This slice locks current-base behavior where Type 3 fonts use escaped glyph names in both `/Encoding Differences` and `/CharProcs` keys, with no `/ToUnicode` CMap. The focused fixture verifies that decoded glyph names drive CharProc-derived Unicode fallback and that `d0`/`d1` CharProc widths still override stale `/Widths` values when grouping WordPress-import text.

The non-overlap target is escaped Type 3 glyph-name parsing at the CharProc text/width boundary. It does not repeat ordinary Type 3 glyph-name CMap coverage, text-state boundary handling, operand-count guards, comments in `/Encoding`, or CharProc width precedence for unescaped names.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcEscapedGlyphNameUnicodeCurrentBaseTest.php` => 1 test file, 12 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*CurrentBaseTest.php` => 61 test files, 711 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charproc-escaped-glyph-unicode-currentbase.php` => exits 0 and emits `WideBlock` plus `thin text` paragraphs with escaped glyph-name and CharProc-width flags true.
- `php -l lanes/markerpdf/tests/PdfFontType3CharProcEscapedGlyphNameUnicodeCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-type3-charproc-escaped-glyph-unicode-currentbase.php` => no syntax errors.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF parser primitives for PDF-name decoding, Type 3 CharProc dictionary lookup, glyph-name-to-Unicode fallback, and CharProc metric extraction.
