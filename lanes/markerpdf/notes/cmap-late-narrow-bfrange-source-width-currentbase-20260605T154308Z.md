# markerPDF CMap late narrow bfchar source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T154308Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T154308Z`

Base accepted HEAD: `cc5990fba07cfe24ac4db3a1208b8183f8821c17`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through the pdftext/PDF parser boundary before Marker turns page dictionaries into text spans, lines, and Markdown. Under the current no-GPU lane rule, this PHP slice maps the native CMap/text-width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

The bounded PDF behavior is ToUnicode CMap source selection when an early broad `beginbfrange` remains as a lazy range but later exact, narrower `beginbfchar` rows cover the same content bytes. The later exact source rows must win before decoded text, extracted runs, styled-span bboxes, and source-width paragraph gap decisions are emitted.

## Implementation

`PdfTextExtractor::toUnicodeSourceLength()` now scans all possible source lengths so a shorter direct CMap row can be found even after a longer range-only candidate matches. When the direct rows cover the remaining operand, `directMappedSourceRemainderCanCover()` lets those exact rows override the stale lazy range for both text decoding and source-width grouping.

## Evidence

Red-first focused check after adding the fixture and before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL uses late narrow ToUnicode bfchar rows before stale broad bfrange source-width fallback on current base
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => '杂楄 歆浈',)
1 test files, 279 assertions, 1 failures
```

Passing focused check after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 290 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-late-narrow-bfrange-source-width-currentbase.php
```

The smoke emits `late_narrow_bfchar_rows_applied=true`, `text_runs_preserved=true`, `source_width_spans_applied=true`, `stale_broad_bfrange_excluded=true`, `false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders the Gutenberg paragraph `ABCD EFGH`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H source widths, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID-map fallback, horizontal/vertical `TJ` adjustment gap handling, odd hex padding, repeated zero-padding, explicit longer ToUnicode rows over narrow codespaces, malformed mixed-width `bfrange` rejection, predefined and object-valued `usecmap`, late same-width ToUnicode `bfchar` ordering, CID CMap source ordering, large lazy `bfrange`, or Encoding CMap `notdef` rows.

The bounded behavior is specifically later exact one-byte ToUnicode `bfchar` rows overriding an earlier broad two-byte lazy `bfrange` before source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, ToUnicode decoder, CIDFont width parser, text run/line/styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
