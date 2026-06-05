# markerPDF CMap source-width codespace padding fallback

Session: `port-dev-markerpdf-source-width-20260605T023925Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T023925Z`
Base accepted HEAD: `c875ac9271a83e1f32853d36d56f4807ebfe7a2e`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through the pdftext/PDFium text extraction boundary before Marker assembles page dictionaries into lines, spans, and Markdown. Under the current no-GPU directive, the PHP fallback owns the native text/CMap/font-width behavior needed before WordPress import without running Python, OCR/model workers, pypdfium/PDFium, PIL, or external PDF tools.

This slice stays inside the accepted CMap source-width fallback cluster. The new bounded edge is a damaged Type0 font whose `/Encoding` CMap name is unresolved, while `/ToUnicode` declares one-byte source codes and the descendant CIDFont `/W` proves that zero-padded text-showing bytes such as `<0041>` should be treated as one source glyph for advance grouping. The fallback must not count the leading `00` padding bytes as separate 500-unit glyphs.

## Implementation

`PdfTextExtractor::zeroPaddedSourceKeysForFontWidths()` now allows the existing zero-padding collapse even when a ToUnicode CMap has a codespace range, as long as the zero prefix is not mapped, the suffix is mapped, and CID width evidence exists for the combined source key. Valid one-byte source keys remain unchanged because exact mapped keys are still accepted before the padding-collapse branch.

The WordPress smoke `wordpress-pdf-cmap-source-width-fallback-import.php` now reports the new `codespace_padding_*` flags and emits the recovered `ABCD EFGH` paragraph for the unresolved-Encoding fallback fixture.

## Evidence

Red-first focused check after adding the fixture, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL uses ToUnicode mapped source bytes before one-byte codespace padding fallback on current base
Expected: ['ABCD EFGH']
Actual: ['ABCDEFGH']
1 test files, 90 assertions, 1 failures
```

Passing focused check after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 99 assertions, 0 failures
```

Adjacent CMap/font-width guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
8 test files, 873 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `codespace_padding_tounicode_source_widths_applied=true`, `codespace_padding_runs_preserved=true`, `codespace_padding_false_join_excluded=true`, `codespace_padding_span_widths=true`, and the no-Python/no-model/no-external-tool execution flags.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded no-codespace source-width fallback, predefined Identity-H source-width fallback, predefined UCS2-H fallback, CIDFont `/DW` fallback, metric-miss ToUnicode `/W` or `/DW` fallback, horizontal or vertical `TJ` adjustment gap handling, `extractTextRuns()` TJ parity, odd hex right-padding, Type0 Encoding CMap CID width priority, UseCMap inheritance, indirect `/W`/`DW`/`W2` operands, CIDSet grouping, Type3 CMap width grouping, quote-operator spacing, vertical `/W2`, or styled-span width-advance geometry.

The new boundary is specifically ToUnicode one-byte codespace padding fallback when the font Encoding CMap is unresolved but descendant CIDFont widths prove combined zero-padded source CIDs before WordPress paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, CIDFont width metrics, CMap source tokenizer, content-token parser, and WordPress smoke path. Full upstream OCR/model runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
