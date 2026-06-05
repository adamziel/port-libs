# markerPDF CMap Partial CIDMap Source Width

Session: `port-dev-markerpdf-source-width-20260605T094739Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T094739Z`
Base accepted HEAD: `54d4990abd113041d05e6000e22de0cf52a8be6c`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into blocks, spans, and Markdown. Under the current no-GPU directive, this PHP lane maps the native searchable-PDF text-showing, Type0 CMap, and font-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. Type0 `/Encoding` CMaps map source character codes to descendant CIDFont CIDs, and descendant `/W`, `/DW`, `/W2`, `/DW2`, or `/CIDSet` metrics are keyed by those CIDs. When a malformed broad codespace groups bytes into two-byte source chunks but only some chunks have direct CIDFont metric evidence, the native width path should preserve those direct chunks while splitting only metric-missing chunks through explicit CMap CID rows.

## Implementation

`PdfTextExtractor::cidMappedSourceKeysForFontWidthsWhenCodeSpaceMiss()` now falls back per source chunk when broad CID CMap source keys have partial direct metrics. Direct metric chunks remain intact. Metric-missing chunks are split using CID-map-only segmentation if every fallback key is explicitly CID-mapped and has width evidence through `/W`, `/DW`, or `/CIDSet`.

Visible text decoding is unchanged. The repair is limited to glyph advance, source-space word spacing, line grouping, and styled-span bbox geometry.

## Red/Green Evidence

Baseline before adding this fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 216 assertions, 0 failures
```

Red-first focused run after adding the fixture and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL falls back per chunk when Type0 CID CMap has partial metric evidence on current base
Expected first span bbox: [0.0, 0.0, 36.0, 12.0]
Actual first span bbox: [0.0, 0.0, 18.0, 12.0]
1 test files, 222 assertions, 1 failures
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 226 assertions, 0 failures
```

Adjacent CMap/font-width family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(CMapSourceWidth|Font.*(Width|CMap|CID|Type0|Type3)).*Test\.php$' | sort)
48 test files, 870 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-partial-cidmap-source-width-currentbase.php
```

The smoke emits `partial_cidmap_metric_miss_widths_applied=true`, `text_runs_preserved=true`, `word_gap_preserved=true`, `false_default_width_excluded=true`, `nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1687 -> 1688`
- `wordpressScenarios`: `1548 -> 1549`
- Focused CMap source-width assertions: `216 -> 226`
- Mapped upstream denominator unchanged; this is an additive current-base PHP behavior inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, default `/DW` fallback, metric-miss ToUnicode fallback, partial metric-miss repair without CID CMap rows, horizontal/vertical `TJ` gaps, odd hex padding, one-byte codespace padding, repeated zero padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined ToUnicode `usecmap`, explicit low CID rows, zero-padded remapped CID ranges, broad ToUnicode codespace recovery, notdef ranges, or high CID range expansion.

The bounded behavior is specifically Type0 Encoding CMap partial metric-miss fallback where direct metric source chunks remain intact and only later broad metric-missing chunks split through explicit CID-map rows before WordPress paragraph grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, content-token parser, source-width text advance path, styled-span extraction, and WordPress smoke renderer. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
