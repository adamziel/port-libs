# markerPDF CMap CIDChar Codespace Source Width

Session: `port-dev-markerpdf-source-width-20260605T063846Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T063846Z`
Base accepted HEAD: `a5c6eedbe4f7a5498e61eab3a11e6ebc18f133a6`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this native PHP lane maps the in-scope PDF text-showing, CMap, and font-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. Type0 `/Encoding` CMaps map source character codes to descendant CIDFont glyph CIDs, and descendant `/W`, `/DW`, `/W2`, `/DW2`, or `/CIDSet` metrics are keyed by those CIDs. If a damaged Encoding CMap declares an over-broad two-byte codespace but still contains explicit one-byte `begincidchar` rows, the explicit source rows are better evidence for font-width grouping than combined default-width chunks.

## Implementation

`PdfTextExtractor::textOperandSourceKeysForFontWidths()` now checks for a CID-map-only source-key segmentation when broad `cidCodeSpaceRanges` produce source chunks without direct font metric evidence. The fallback is accepted only when:

- the font has a CID CMap;
- the map-only segmentation creates more granular source keys;
- every fallback source key is explicitly CID-mapped;
- the broad source chunks do not already have direct font metrics; and
- the fallback keys have width evidence through `/W`, `/DW`, or `/CIDSet`.

Visible text decoding is unchanged. The repair is limited to glyph advance, source-space word spacing, line grouping, and styled-span bbox geometry.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 font fixture with:

- `/Encoding` CMap codespace `<0000> <FFFF>`;
- explicit `begincidchar` rows `<41>` through `<48>` mapping to CIDs 65 through 72;
- `/ToUnicode` one-byte mappings for `A` through `H`;
- descendant CIDFont `/W [65 68 1000 69 72 250]`; and
- two one-byte source operands positioned so the broad codespace path collapses the first span bbox to a two-chunk default-width result.

Before the source fix, the new case decoded visible text but failed geometry:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL prefers explicit CID CMap source rows over malformed broad codespace before width grouping on current base
Expected first span bbox: [0,0,48,12]
Actual first span bbox: [0,0,12,12]
1 test files, 170 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 174 assertions, 0 failures
```

Adjacent CMap/font-width regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
13 test files, 1090 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `explicit_cid_rows_codespace_recovered=true`, `explicit_cid_rows_codespace_runs_preserved=true`, `explicit_cid_rows_codespace_combined_chunk_width_excluded=true`, `explicit_cid_rows_codespace_false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1532 -> 1533`
- `wordpressScenarios`: `1428 -> 1429`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font-width source boundary.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H fallback, CIDFont default `/DW` fallback, metric-miss ToUnicode fallback, partial CID metric-miss repair, horizontal/vertical `TJ` adjustment gap repair, odd hex padding, one-byte codespace padding fallback, repeated zero-padded source-byte collapse, explicit longer ToUnicode source-key precedence, malformed mixed-width ToUnicode `bfrange` rejection, predefined ToUnicode `usecmap` inheritance, Type0 Encoding CMap CID width priority with valid codespace, indirect `/W`/`DW`/`W2`, CIDSet grouping, Type3 CMap width grouping, or vertical `/W2` geometry.

The new boundary is specifically explicit Type0 Encoding CMap CID source-row recovery when malformed broad codespace ranges would otherwise drive combined default-width chunks before WordPress paragraph geometry.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, ToUnicode parser, Type0 Encoding CMap parser, CIDFont width metrics, CMap source tokenizer, content-token parser, text-run/line/styled-span extraction, and WordPress smoke renderer. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
