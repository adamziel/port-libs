# markerPDF CMap TJ run source-width gap

Session: `port-dev-markerpdf-source-width-20260604T235347Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260604T235347Z`
Base accepted HEAD: `d06a1fbbeb92cce81238ad305c8b5a4fc4d9e7b6`

## Source Truth

The pinned upstream `sddai/markerPDF` pipeline routes searchable PDF text through the pdftext dictionary-output boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, the PHP fallback mirrors the native PDF text-showing behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, or external PDF tools.

This slice stays inside the previously mapped CMap source-width fallback cluster. PDF `TJ` arrays can interleave text operands with numeric positioning adjustments; the native line/styled paths already used source-width glyph advances to insert a visual word gap for `[<41424344> -1000 <45464748>] TJ`, but `extractTextRuns()` still decoded the same array as `ABCDEFGH`.

## Implementation

`PdfTextExtractor::textRunsFromContentStream()` now carries the same font-size, character-spacing, word-spacing, horizontal-scale, and text-matrix horizontal-scale state used by the line/styled extractors. Visible text runs now call `decodePositionedTextOperand()`, so source-width-backed `TJ` numeric adjustments are reflected consistently in run output.

The WordPress smoke `wordpress-pdf-cmap-source-width-fallback-import.php` now reports that `extractTextRuns()` preserves `ABCD EFGH` and excludes the false joined `ABCDEFGH` run.

## Evidence

Red-first focused check after adding the run assertion, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL preserves TJ source-width adjustment gaps in extracted text runs on current base
Expected: ['ABCD EFGH']
Actual: ['ABCDEFGH']
1 test files, 48 assertions, 1 failures
```

Passing focused check after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
PASS falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base
PASS inserts TJ adjustment word gap after CMap source-width fallback on current base
PASS preserves TJ source-width adjustment gaps in extracted text runs on current base
1 test files, 51 assertions, 0 failures
```

## Non-Overlap

This does not repeat prior zero-padded source-width fallback, CIDFont default `/DW` source fallback, predefined `/Identity-H` source-width fallback, metric-miss ToUnicode fallback, or the earlier `TJ` line/styled-span gap repair. The new boundary is specifically `extractTextRuns()` parity for source-width-aware `TJ` positioning before WordPress paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width metrics, content-token parser, and WordPress smoke path. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
