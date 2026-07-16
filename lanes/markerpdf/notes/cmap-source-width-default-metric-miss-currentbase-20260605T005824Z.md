# markerPDF CMap Source Width Default Metric Miss Current Base

Session: `port-dev-markerpdf-source-width-20260605T005824Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T005824Z`

Base accepted HEAD: `41cae8b6fd1e5314059c74ad58c304aea88484db`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker builds page/block/line/span output.
- The native PHP no-GPU fallback must preserve the PDF text source-code boundaries and descendant CIDFont advance widths before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- In PDF Type0 text extraction, ToUnicode CMap source codes define the character-code segmentation used for text mapping. CIDFont `/DW` is valid default width evidence for CIDs not listed in `/W`, so it must validate the ToUnicode fallback segmentation when predefined `/Identity-H` chunks miss direct `/W` or CIDSet evidence.

## Behavior Added

`PdfTextExtractor::toUnicodeSourceKeysForFontWidthsWhenCidMetricsMiss()` now accepts ToUnicode source-key fallback segmentation when every fallback key is mapped by the ToUnicode CMap and the only available width evidence is CIDFont `/DW`.

The direct Identity-H chunk check still ignores `/DW`, so broad default widths do not block fallback. The fallback candidate is also rejected when it contains unmapped padding keys, preserving the accepted zero-padded `/DW` source-width behavior.

Visible text decoding is unchanged. The change is bounded to glyph advance segmentation, same-line gap decisions, source-space counting, and styled-span bbox width.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 `/Identity-H` font fixture with:

- a ToUnicode CMap declaring one-byte source codes `<41>` through `<48>`;
- one-byte text operands `<41424344>` and `<45464748>`;
- a descendant CIDFont with `/DW 1000` and no `/W` or `/CIDSet`;
- a second `Tm` positioned so invalid Identity-H chunking creates a false `ABCD EFGH` WordPress word gap, while ToUnicode source-key segmentation keeps `ABCDEFGH` joined.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back to ToUnicode source widths when Identity-H chunks only have default CID metrics on current base
Expected: array (0 => 'ABCDEFGH',)
Actual: array (0 => 'ABCD EFGH',)
1 test files, 62 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
PASS falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base
PASS falls back to ToUnicode source widths when Identity-H chunks only have default CID metrics on current base
PASS inserts TJ adjustment word gap after CMap source-width fallback on current base
PASS preserves TJ source-width adjustment gaps in extracted text runs on current base
PASS pads odd-length hex string operands on the right before CMap source-width fallback on current base
1 test files, 71 assertions, 0 failures
```

Adjacent CMap/font regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 823 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `identity_default_metric_miss_tounicode_widths_applied=true`, `identity_default_metric_miss_false_gap_excluded=true`, `identity_default_metric_miss_span_widths=true`, and native-only execution flags, while preserving the accepted zero-padded `/DW`, explicit `/W`, `TJ`, and odd-hex source-width scenarios.

## Status Delta

- `phpPass`: `1214 -> 1215`
- `wordpressScenarios`: `1191 -> 1192`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font source-width fallback cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, CIDFont `/DW` zero-padded fallback, predefined `/Identity-H` source widths, explicit `/W` metric-miss fallback, `TJ` line/styled/run gap handling, odd-length hex right-padding, Type0 Encoding CMap CID width priority, indirect `/W`/`DW`/`W2` parsing, CIDSet grouping, Type3 CMap/CIDSet width grouping, quote-operator spacing, vertical `/W2`, scaled text-matrix advance, or xref/parser boundaries. The new boundary is specifically Identity-H metric-miss fallback to mapped ToUnicode source keys when CIDFont `/DW` is the only width source.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, CIDFont width metric parser, text-position grouping path, styled-span bbox path, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
