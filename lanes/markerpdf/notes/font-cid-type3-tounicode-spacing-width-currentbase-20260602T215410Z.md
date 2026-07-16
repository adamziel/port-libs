# markerPDF font CID Type3 ToUnicode spacing width current base

Micro-slice: `font-cid-type3-tounicode-spacing-width-currentbase`

Base accepted HEAD: `46b872b82e6663ed85da04f0c1274e2577b1e5b5`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to the pdftext/PDFium stack before markerPDF assembles page/block/line/span dictionaries. The native PHP fallback therefore has to perform CMap source segmentation, ToUnicode decoding, text-state spacing, and CIDFont/Type3 width grouping before WordPress paragraphs are emitted.

Relevant parser behavior for this reduced native slice: text showing advances use source glyph boundaries and font metrics, `/Tw` word spacing applies to source space glyphs, and non-Identity CMaps can map a nonliteral source code to CID 32 before ToUnicode maps that same source code to visible Unicode text.

## Implementation

`PdfTextExtractor::fontMapFromFontBody()` now marks any font map with an Encoding CMap CID map as `wordSpacingUsesCidMap`, instead of limiting that path to Type3 fonts. The existing `sourceKeyUsesWordSpacing()` path then counts a source key as a word-spacing glyph when the active font CMap maps it to CID 32.

This keeps the accepted Type3 CMap behavior and adds the same current-font source-space handling for Type0 CID fonts. It does not change ToUnicode decoding, CID width parsing, Type3 width parsing, or page-resource selection.

## Focused Fixture

`PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php` builds one current page with:

- `/Fcid`, a Type0 font whose `/Encoding` CMap maps `<F020>` to CID 32, whose ToUnicode maps `<F020>` to U+2060, and whose descendant CIDFont `/W` widths keep the source glyph advances at 500 units.
- `/Ft3`, a Type3 CMap font whose `/Encoding` CMap maps `<E020>` to CID 32, whose ToUnicode maps `<E020>` to U+2060, and whose `/Widths` array supplies the same advance.
- `18 Tw` and same-line `Tm` positioning chosen so missing source word spacing creates a false visible gap.

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses current CID and Type3 ToUnicode source CIDs for word spacing and width grouping
Expected: ['A\u2060BC', 'D\u2060EF']
Actual:   ['A\u2060B C', 'D\u2060EF']
1 test files, 1 assertions, 1 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
8 test files, 656 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cid-type3-tounicode-spacing-width-currentbase.php
type0_cid32_word_spacing_applied=true
type3_cid32_word_spacing_preserved=true
tounicode_word_joiner_preserved=true
raw_source_codes_excluded=true
paragraphs: A\u2060BC; D\u2060EF
```

## Status Delta

- `lane-status.json`: `phpPass` and `wordpressScenarios` move `870 -> 871`.
- `UPSTREAM_TEST_MANIFEST.json`: mapped behavior count moves `614 -> 615` with `pdfFontCidType3ToUnicodeSpacingWidthCurrentBase`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-type3-tounicode-spacing-width-currentbase.php`
- `php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $p) { json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$p: ".json_last_error_msg()."\n"); exit(1); } echo "$p: valid json\n"; }'`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `php lanes/markerpdf/examples/wordpress-pdf-font-cid-type3-tounicode-spacing-width-currentbase.php`
- `git diff --check -- lanes/markerpdf`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, ToUnicode parser, CIDFont `/W` metrics, Type3 `/Widths`, PDF text-state spacing, and WordPress smoke paths already in `lanes/markerpdf`. Full upstream runner parity remains gated on the existing Python/pdftext/PDFium/Surya/model/runtime stack and was not attempted in this isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type3-only Encoding CMap spacing, named Type0 CMap resource width grouping, indirect CIDFont `/W` parsing, Type3 CIDSet/default-width grouping, Type3 CharProc ToUnicode mapping, ToUnicode surrogate CID width grouping, direct Type0 resource dictionaries, page-resource ToUnicode/width scoping, or xref/current trailer repair. The new behavior is specifically applying Encoding CMap CID 32 source word spacing to Type0 CID fonts while preserving the already accepted Type3 current-font spacing path.
