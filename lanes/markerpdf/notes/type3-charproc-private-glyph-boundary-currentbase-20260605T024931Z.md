# markerPDF Type3 CharProc private glyph boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T024931Z`

Base accepted HEAD: `1eee6af798a6b3fb39aedd5a1a8249d05194afe5`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR, layout, table, or equation model work. In that parser boundary,
Type3 `/CharProcs` streams are glyph programs. When a Type3 font has no
`/ToUnicode`, the native PHP fallback may recover text from standard Adobe
glyph names selected by the active `/Encoding`, but an unused private CharProc
name is not visible page text and should not poison recoverable standard glyphs.

## Red Check

After adding the focused fixture and before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WIDEBLOCK',
  1 => 'thin text',
)
Actual: array (
)
```

That proved a single unused `/Private.UnusedGlyph` CharProc key caused the
no-`/ToUnicode` Type3 glyph-name fallback to abort, even though the visible
source bytes mapped through a CMap to standard glyph names.

## Implementation

`PdfTextExtractor::type3StandardCharProcUnicodeByName()` now skips unmappable
private CharProc names instead of returning `null` for the whole font. It still
returns `null` when no standard glyph-name mapping can be recovered.

The focused fixture proves:

- standard `/W`, `/I`, `/D`, `/E`, `/B`, `/L`, `/O`, `/C`, `/K`, `/t`, `/h`,
  `/i`, `/n`, `/e`, and `/x` CharProc names decode CMap source bytes without
  `/ToUnicode`;
- the unused private CharProc stream does not block text recovery;
- private and standard CharProc payload text remains excluded from visible
  WordPress paragraphs;
- Type3 CharProc width boundaries still keep `WIDEBLOCK` joined and
  `thin text` separated.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
```

Result: `15 test files, 127 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charproc-private-glyph-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WIDEBLOCK` and `thin text`, with
`standard_charprocs_decode_text=true`, `unused_private_charproc_ignored=true`,
`charproc_payload_visible_text_excluded=true`, `wide_width_boundary_preserved=true`,
`thin_width_boundary_preserved=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Additional local checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charproc-private-glyph-boundary-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
Type3 CharProc dictionary parser, CMap parser, Adobe glyph-name fallback table,
text-source segmentation, CharProc width grouping, and WordPress smoke path.
No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc
fallback exclusion, same-number CharProc stream generation selection, indirect
CharProcs dictionary exact-generation selection, top-level `/CharProcs` lookup,
nested CharProcs dictionary parsing, Type3 subtype gating, Type3 FontMatrix
normalization, filtered CharProc fail-closed decoding, Type3 Encoding
Differences, named/base Encoding color glyph widths, Type3 CMap/CIDSet
grouping, Type3 descriptor `MissingWidth`, Type0 CID widths, or xref/object
stream repair. The new boundary is specifically no-`/ToUnicode` Unicode
recovery when a Type3 `/CharProcs` dictionary contains unused private glyph
programs alongside standard encoded glyphs.

## Follow-Up

An exploratory broader run of `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
still fails on the accepted-base hybrid-xref test with an unrelated
`$previousOffset` warning in `xrefEntriesFromOffsetChain()`. This Type3 slice
does not edit that xref cluster; the integrator should treat it as a separate
current-base xref follow-up, not as GPU/model scope.
