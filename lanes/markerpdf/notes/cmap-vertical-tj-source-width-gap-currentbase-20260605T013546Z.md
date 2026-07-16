# markerPDF CMap vertical TJ source-width gap

Session: `port-dev-markerpdf-source-width-20260605T013546Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T013546Z`
Base accepted HEAD: `8501d438d0b0971a6a00bb6327402c508aeb24d9`

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through the pdftext/PDFium text boundary before converting page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU markerPDF directive, this native PHP slice maps the in-scope PDF text-showing behavior without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

PDF `TJ` arrays may interleave string operands and numeric positioning adjustments. The accepted horizontal source-width repair used CMap source CID widths to recover gaps such as `ABCD EFGH`; this slice adds the matching vertical Type0/CMap boundary where `/Identity-V` plus descendant `/W2` metrics must preserve a word-sized numeric adjustment inside one `TJ` array.

## Implementation

`PdfTextExtractor::decodePositionedTextOperand()` now handles vertical writing mode separately instead of returning the plain decoded array text. For vertical CMap arrays it advances through each source text operand with `glyphVerticalDisplacementsForTextOperand()`, applies numeric adjustments with `adjustTextEndY()`, and inserts a pending word gap when the source-CID `/W2` path shows a word-sized vertical adjustment.

The existing WordPress smoke `wordpress-pdf-cmap-source-width-fallback-import.php` now includes a vertical `Identity-V` fixture and emits `Vert Import` instead of the false joined `VertImport`.

## Evidence

Red-first focused check after adding the vertical `TJ` assertion, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL preserves vertical TJ source-width adjustment gaps before WordPress text grouping on current base
Expected: ['Vert Import']
Actual: ['VertImport']
1 test files, 72 assertions, 1 failures
```

Passing focused check after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
PASS preserves vertical TJ source-width adjustment gaps before WordPress text grouping on current base
1 test files, 79 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke metadata includes:

- `vertical_tj_adjustment_source_width_gap_applied=true`
- `vertical_tj_adjustment_source_width_runs_gap_applied=true`
- `vertical_tj_adjustment_false_join_excluded=true`
- `vertical_tj_adjustment_runs_false_join_excluded=true`
- `vertical_tj_adjustment_span_bbox_preserved=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, CIDFont default `/DW` source fallback, predefined `/Identity-H` source widths, metric-miss ToUnicode fallback, odd-length hex right padding, horizontal `TJ` line/styled-span gap handling, or horizontal `extractTextRuns()` `TJ` parity. The new boundary is specifically vertical `/Identity-V` `TJ` numeric adjustments using source CID `/W2` displacements before WordPress paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont vertical width metrics, content-token parser, and WordPress smoke path. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
