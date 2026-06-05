# markerPDF CMap mixed-width bfrange source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T052805Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T052805Z`
Base accepted HEAD: `4730cc6d01f7dd13815bc0b8f8150bc3c9a09645`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this PHP lane maps the native PDF CMap/font-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. PDF CMap `beginbfrange` rows use fixed-width source-code strings; the native CID CMap parser already rejected ranges whose normalized source start/end widths differ. The ToUnicode parser did not, so a malformed `<00> <FFFF> <0030>` range could override valid one-byte `bfchar` mappings and corrupt the zero-padded source-width fallback text before WordPress paragraph rendering.

## Implementation

`PdfTextExtractor::parseToUnicodeRanges()` now skips ToUnicode `bfrange` rows when the normalized source start and end keys have different widths. This mirrors `parseCidRanges()` and keeps malformed broad ranges from inventing one-byte decoy source mappings.

The focused fixture keeps valid `<41>` through `<48>` `bfchar` rows, then adds the malformed mixed-width range. Before the fix, `<0041004200430044>` decoded through the malformed range as `0q0r0s0t`. After the fix, the malformed range is ignored, valid source keys remain authoritative, and the existing CMap source-width fallback preserves `ABCD EFGH`, two text runs, and CIDFont span bboxes.

## Evidence

Red-first focused check after adding the fixture and before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores mixed-width ToUnicode bfrange rows before CMap source-width fallback on current base
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => '0q0r0s0t0u0v0w0x',)
1 test files, 143 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores mixed-width ToUnicode bfrange rows before CMap source-width fallback on current base
1 test files, 154 assertions, 0 failures
```

Adjacent CMap/font-width guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 994 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `mixed_width_bfrange_ignored=true`, `mixed_width_bfrange_runs_preserved=true`, `mixed_width_bfrange_decoy_range_excluded=true`, `mixed_width_bfrange_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1469 -> 1470`
- `wordpressScenarios`: `1384 -> 1385`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font-width source boundary.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H fallback, CIDFont default `/DW` fallback, metric-miss ToUnicode fallback, partial CID metric miss repair, horizontal/vertical `TJ` adjustment gap repair, odd hex padding, one-byte codespace padding fallback, repeated zero-padded source-byte collapse, explicit longer `bfchar` source-key precedence, Type0 Encoding CMap CID width priority, UseCMap inheritance, indirect `/W`/`DW`/`W2` operands, CIDSet grouping, Type3 CMap width grouping, quote-operator spacing, or vertical `/W2` geometry. The new boundary is specifically rejecting malformed mixed-width ToUnicode `bfrange` rows before source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, ToUnicode CMap parser, CIDFont width metrics, CMap source tokenizer, content-token parser, text-run/line/styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
