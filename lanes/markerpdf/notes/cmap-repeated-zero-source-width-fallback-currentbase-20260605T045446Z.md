# markerPDF CMap repeated zero source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T045446Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T045446Z`
Base accepted HEAD: `48fd42b5dca68647d1ddff43b51b8403b4c5825c`

## Source Truth

The pinned upstream `sddai/markerPDF` pipeline routes searchable PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this PHP lane maps the native PDF text-showing, CMap, and font-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. Some searchable PDFs carry padded source operands such as `<00000041>` while the recoverable ToUnicode CMap only exposes a shorter suffix mapping such as `<41> <0041>`. When descendant CIDFont metrics prove the combined source CID, the native extractor should collapse the repeated leading zero source-code units into one width source instead of counting padding bytes as separate glyph advances.

## Implementation

`PdfTextExtractor::zeroPaddedSourceKeysForFontWidths()` now scans through repeated all-zero source-code units before a mapped non-zero suffix. When the combined source key is within the four-byte CMap source limit and the descendant CIDFont width map contains that CID, the helper emits the combined source key for font-width grouping.

The focused fixture uses one-byte ToUnicode mappings for `<41>` through `<48>`, two text operands made from repeated zero-padded source codes, and CIDFont `/W [65 68 1000 69 72 250]`. Before the fix, padding bytes caused the extractor to join the runs as `ABCDEFGH`; after the fix, it emits `ABCD EFGH`, preserves the two text runs, and keeps the styled span bboxes at `[0,0,48,12]` and `[48,0,60,12]`.

## Evidence

Red-first focused check after adding the new fixture and before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL collapses repeated zero-padded source bytes before CMap source-width fallback on current base
Expected: ['ABCD EFGH']
Actual: ['ABCDEFGH']
1 test files, 133 assertions, 1 failures
```

Passing focused check after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 142 assertions, 0 failures
```

Adjacent CMap/font-width family check after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
11 test files, 1009 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `repeated_zero_padding_source_widths_applied=true`, `repeated_zero_padding_runs_preserved=true`, `repeated_zero_padding_false_join_excluded=true`, `repeated_zero_padding_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted single zero-padded source-width fallback, predefined Identity-H/UCS2-H fallback, CIDFont default `/DW` fallback, metric-miss ToUnicode fallback, partial CID metric miss repair, horizontal/vertical `TJ` adjustment gap repair, odd hex padding, one-byte codespace padding fallback, or explicit longer source-key precedence. The new boundary is repeated zero-padded source-code-unit collapse before CIDFont source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, content-token parser, CMap parser, ToUnicode decoder, CIDFont width metrics, text-run/line/styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
