# markerPDF Type0 Type3 broader font behavior current-base

Micro-slice: `font-type0-type3-broader-behavior-currentbase`

## Scope

This slice covers two native searchable-PDF font gaps for `plib-tuzwg.9`:

- Type0 fonts whose `/DescendantFonts` value is a direct dictionary or a direct indirect-reference dictionary instead of the usual array wrapper.
- Type3 fonts that use an Encoding CMap plus `/CharProcs` `d0`/`d1` width vectors when no `/Widths` array is present.

It intentionally excludes predefined CMap diagnostics, TrueType width/name-index variants, encrypted PDFs, table/layout/OCR/model behavior, Python/pdftext execution, and external PDF tools.

## Behavior

`PdfTextExtractor` now accepts non-array Type0 `/DescendantFonts` values when they resolve to a single CIDFont dictionary, preserving descendant `/DW`/`/W` width metrics and `FontDescriptor` flags before WordPress text-gap grouping.

For Type3 fonts, CharProc width extraction now receives the active CID encoding map. When a CharProc glyph name resolves to a single Unicode scalar/CID, its `d0`/`d1` vector is transformed through the font matrix and stored under the encoded CID. That lets CMap-encoded Type3 fonts without `/Widths` arrays use glyph-program widths for native text advance grouping while still excluding CharProc drawing payload text.

## Red-First Evidence

Before the implementation, the focused fixture failed both assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0Type3BroaderBehaviorCurrentBaseTest.php
FAIL uses direct-referenced Type0 DescendantFonts dictionaries before WordPress width grouping on current base
FAIL uses Type3 CMap CharProc widths when Widths arrays are absent on current base
```

The Type0 case emitted `Wide Block` instead of `WideBlock`, and the Type3 case emitted `WIDE BLOCK` plus `thintext` instead of `WIDEBLOCK` and `thin text`.

## Verification

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0Type3BroaderBehaviorCurrentBaseTest.php
1 test files, 16 assertions, 0 failures
```

Adjacent font shard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0Type3BroaderBehaviorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php
16 test files, 134 assertions, 0 failures
```

Broader font current-base shard:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfFont.*CurrentBaseTest\.php$' | sort)
127 test files, 2333 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType0Type3BroaderBehaviorCurrentBaseTest.php
```

JSON/whitespace/marker checks:

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check
```

## Non-Overlap

This does not change accepted Type0 resource CMap descriptor-width behavior, predefined CMap diagnostics, Type3 CharProc ToUnicode glyph-name recovery, simple Type3 CMap spacing with explicit width arrays, Type3 CharProc generation precedence, TrueType width/name-index handling, encryption preflight, table/layout/OCR behavior, or any Python/pdftext/model path.
