# Late Large CMap CIDRange Source-Width Fallback

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T181435Z`
Base: `9ead64905fb753cca25bfab3c1ec066d02d22a57`

## Source Truth

PDF CMap mappings are order-sensitive: later overwriting `begincidrange` rows must replace earlier direct `begincidchar` rows for the same source codes. The existing native markerPDF path already retains large CID ranges lazily when eager expansion would be too broad; this slice fixes the stale direct-map interaction for that lazy path.

## Implementation

`PdfTextExtractor::parseCidRanges()` now removes same-width direct CID mappings covered by a later overwriting CID range before retaining the lazy range row. This keeps `cidForWidthSourceKey()` from returning stale earlier `cidchar` CIDs before consulting the authoritative later range.

The non-overwriting `notdef` path is unchanged, and no GPU/model/OCR/PDFium/external PDF tooling was added.

## Evidence

Red-first after adding the focused case only:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
# 1 test files, 11 assertions, 1 failures
# Expected ABCDEFGH; actual ABCD EFGH
```

Focused after the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
# 1 test files, 20 assertions, 0 failures
```

Adjacent CMap/font-width family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php
# 7 test files, 372 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-cmap-late-cidrange-source-width-currentbase.php
# emits Gutenberg paragraph ABCDEFGH and smoke flags:
# later_large_cidrange_override_applied=true
# text_runs_preserved=true
# late_cidrange_span_widths_applied=true
# stale_cidchar_false_gap_excluded=true
# raw_nul_bytes_excluded=true
# executes_python_or_models=false
# executes_external_pdf_tools=false
```

## Status Delta

- `phpPass`: `2135 -> 2136`
- `wordpressScenarios`: `1840 -> 1841`

## Dependency Closure

No new support component is needed. This reuses the existing native `pdf-text-dictionary-core` / `PdfTextExtractor` CMap, CID width, and styled-span extraction path.

## Non-Overlap

This does not repeat the accepted negative width metric slice, large CMap source-width fallback slice, large ToUnicode bfrange source-width slice, or the existing later-`cidchar`-over-earlier-`cidrange` order case. The new behavior is the reverse overwrite direction where a later large lazy `cidrange` must clear earlier direct `cidchar` entries before width lookup.
