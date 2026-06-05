# markerPDF Zero-Padded CIDRange Source Width

Session: `port-dev-markerpdf-source-width-20260605T071809Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T071809Z`
Base accepted HEAD: `8bd450f4c90e33ec348f2657c7aae831ed20a4df`

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through the `pdftext` / PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this native PHP lane maps the in-scope Type0 CMap, text-showing, and CIDFont width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. Type0 `/Encoding` CMaps map source character codes to descendant CIDFont CIDs, and descendant `/W`, `/DW`, `/W2`, `/DW2`, or `/CIDSet` metrics are keyed by those CIDs. When a damaged text operand includes leading zero padding before one-byte source codes, an explicit CMap source suffix that maps through `begincidrange` is better width evidence than the padded source code's default CID width.

## Implementation

`PdfTextExtractor::zeroPaddedSourceKeysForFontWidths()` now recognizes a remapped Encoding CMap suffix while collapsing zero padding. If the suffix exists in the CID map, the padded combined key is not itself CID-mapped, and the mapped suffix CID has width evidence, the source-width path uses the suffix key for glyph advance and word-gap decisions.

Visible text decoding is unchanged. The repair is limited to glyph advance, source-space word spacing, line grouping, and styled-span bbox geometry.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 fixture with:

- a malformed broad Encoding CMap codespace `<0000> <FFFF>`;
- an explicit `begincidrange` row `<41> <48> 1`;
- zero-padded source operands `<0041004200430044>` and `<0045004600470048>`;
- `/ToUnicode` one-byte mappings for `A` through `H`;
- descendant CIDFont `/W [1 4 250 5 8 1000]`; and
- a second `Tm` position where default-width fallback falsely joins `ABCDEFGH`, while remapped CID widths preserve `ABCD EFGH`.

Red-first current-base run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL uses zero-padded remapped CID range source widths before default-width fallback on current base
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => 'ABCDEFGH',)
1 test files, 175 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 184 assertions, 0 failures
```

Adjacent CID/CMap/font-width regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
12 test files, 1095 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `zero_padded_cid_range_remap_widths_applied=true`, `zero_padded_cid_range_runs_preserved=true`, `zero_padded_cid_range_default_width_excluded=true`, `zero_padded_cid_range_false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1566 -> 1567`
- `wordpressScenarios`: `1454 -> 1455`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback for identity source CIDs, predefined Identity-H/UCS2-H fallback, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID metric-miss repair, horizontal/vertical `TJ` adjustment gap repair, odd hex padding, one-byte codespace padding fallback, repeated zero-padded source-byte collapse, explicit longer ToUnicode source-key precedence, malformed mixed-width ToUnicode `bfrange` rejection, predefined ToUnicode `usecmap` inheritance, Type0 Encoding CMap CID source-row recovery over malformed broad codespace, valid Type0 Encoding CMap CID width priority, indirect `/W`/`DW`/`W2`, CIDSet grouping, Type3 CMap width grouping, or vertical `/W2` geometry.

The bounded behavior is specifically zero-padded Type0 source operands whose suffix source codes are remapped by an Encoding CMap `begincidrange` before descendant CIDFont width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, ToUnicode parser, Type0 Encoding CMap parser, CIDFont width metrics, CMap source tokenizer, content-token parser, text-run/line/styled-span extraction, and WordPress smoke renderer. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
