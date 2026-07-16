# markerPDF CMap notdef range source-width fallback

Session: `port-dev-markerpdf-source-width-20260606T011540Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T011540Z`
Base accepted HEAD: `f2c703c2cce632c5e768dfab22f6ff2f65875b98`

## Source Truth

The pinned upstream `sddai/markerPDF` searchable-PDF path depends on pdftext/PDF parser CMap and font-width boundaries before Marker builds spans, lines, blocks, and Markdown. Under the no-GPU directive, this PHP lane keeps the work to native PDF parser behavior and does not run OCR, Surya/Torch, PDFium, external PDF tools, or model workers.

Adobe Technical Note 5014 describes `beginnotdefrange` as mapping every input code in a notdef range to one destination CID, unlike `begincidrange` sequential mappings. This matters for WordPress imports because the same source-code-to-CID boundary drives CIDFont `/W` and `/DW` fallback widths before text-gap grouping.

Source reference: https://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5014.CIDFont_Spec.pdf

## Implementation

`PdfTextExtractor::parseCidRanges()` now stores whether a CMap range is sequential. `begincidrange` entries still increment CIDs across a range, while `beginnotdefrange` entries keep the declared notdef CID constant in both eager `cidMap` expansion and delayed `cidRanges` fallback after the CMap scan cap.

`PdfTextExtractor::cidFromCidRangesForSourceKey()` now uses that stored range kind so source-width fallback for large notdef ranges selects the declared notdef CID instead of inventing later sequential CIDs.

## Evidence

Red-first focused check after updating the CMap source-width test and before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL uses constant Encoding CMap notdef range CIDs before source-width fallback on current base
FAIL uses large Encoding CMap notdef range constant CID after source-width scan cap on current base
1 test files, 333 assertions, 2 failures
```

Passing focused check after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 338 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-notdef-source-width-currentbase.php
```

The smoke emits `notdef_range_constant_cid_widths_applied=true`, `sequential_notdef_widths_excluded=true`, `word_gap_preserved=true`, `text_runs_preserved=true`, `raw_source_default_width_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded ToUnicode fallback, odd hex padding, explicit-longer CMap source-key precedence, one-byte codespace padding, CID CMap source row precedence, late `bfchar`/`bfrange` ordering, mixed source-width codespaces, normal `begincidrange` sequential CIDs, `beginnotdefchar` rows, vertical `/W2`, named resource CMaps, Type3 CMap widths, or OCR/model/PDFium handoffs.

The bounded behavior is specifically `beginnotdefrange` constant-CID semantics feeding CIDFont source-width fallback, including delayed range lookup after the eager CMap expansion cap.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, Type0 Encoding CMap CID mapping, CIDFont `/W` and `/DW` metric parser, text-run/line/styled-span extraction, and existing WordPress smoke path. Full upstream model/OCR parity remains out of scope under the no-GPU markerPDF direction.
