# markerPDF CMap Large CID Range Source-Width Fallback

Session: `port-dev-markerpdf-source-width-20260605T121002Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T121002Z`
Base accepted HEAD: `295120098a86970c9ff6f0c0719d64afe0c9dda9`

## Source Truth

Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through pdftext/PDFium before Marker assembles page text, spans, and Markdown. In the native no-GPU PHP lane, CMap source-code boundaries and CIDFont width metrics must be preserved without expanding unbounded CMap ranges, running OCR/model workers, or invoking external PDF tools.

This slice stays inside the existing CMap source-width fallback cluster and adds only the edge where a valid Type0 Encoding CMap `begincidrange` extends beyond the parser's eager expansion cap. The native fallback now keeps the range formula as bounded metadata so source codes past the cap still resolve to descendant CIDFont `/W` metrics before WordPress paragraph grouping.

## Behavior Added

`PdfTextExtractor::parseCidCMap()` now carries `cidRanges` metadata for validated CID ranges while still materializing only the first bounded subset into exact source rows. `PdfTextExtractor::cidForWidthSourceKey()` first honors exact CID rows, then resolves matching lazy ranges by source width and range offset, preserving overwrite/non-overwrite behavior for normal and notdef ranges.

The focused fixture uses `<0000> <1FFF> 1000` and text source codes `<1800>` through `<1807>`. Before the fix, those source codes were beyond `MAX_CMAP_RANGE_ENTRIES`, so width lookup fell back to `/DW 500` and introduced a false WordPress word gap. After the fix, they resolve to CIDs 7144-7151 and use `/W [7144 7147 1000 7148 7151 250]`.

## Evidence

Red-first focused run after adding the fixture, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses large CID CMap ranges past eager expansion cap before source-width fallback on current base
Expected: array (0 => 'ABCDEFGH',)
Actual: array (0 => 'ABCD EFGH',)
1 test files, 1 assertions, 1 failures
```

Passing direct focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses large CID CMap ranges past eager expansion cap before source-width fallback on current base
1 test files, 10 assertions, 0 failures
```

Adjacent CMap/font/text extractor regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1264 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-large-cidrange-source-width-currentbase.php
```

The smoke emits `large_cidrange_widths_applied=true`, `text_runs_preserved=true`, `large_cidrange_default_width_excluded=true`, `false_word_gap_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H source widths, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID-map fallback, horizontal/vertical `TJ` adjustment gap handling, odd hex padding, repeated zero-padding, explicit longer source-key precedence, malformed mixed-width ToUnicode `bfrange` rejection, predefined and object-valued `usecmap` inheritance, high CID range expansion below the eager cap, Encoding CMap notdef ranges/chars, broad-codespace precedence, or late malformed `usecmap` ordering. The bounded behavior is specifically lazy CID range source-to-CID resolution beyond the eager expansion cap for width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, source tokenizer, CIDFont width parser, text-position grouping path, styled-span extraction, and WordPress smoke renderer. Full upstream benchmark/model parity remains out of scope under the current no-GPU markerPDF directive.

Root harness: not run - isolated micro-slice.
