# markerPDF CMap ToUnicode order source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T150345Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T150345Z`

Base accepted HEAD: `5e277f7985f08bbea655de828433799334fd1a1e`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through PDF parser font/CMap decoding before Marker builds page text and Markdown. In this no-GPU PHP lane, native searchable-PDF import owns the CMap parser and CIDFont width boundary.

The bounded behavior here is ToUnicode CMap stream order. A ToUnicode CMap may contain an early broad `beginbfrange` and later exact `beginbfchar` rows for the same source codes. The later exact rows must win before decoded text, text runs, styled spans, and source-width paragraph gap decisions are emitted.

## Implementation

`PdfTextExtractor::parseToUnicodeCMap()` now walks `beginbfchar` and `beginbfrange` blocks in stream order via `cMapToUnicodeMappingBlocks()`, instead of parsing all `bfchar` blocks before all `bfrange` blocks.

`parseToUnicodeRanges()` removes stale direct ToUnicode rows for source codes covered by a later range before recording that range, so source-order precedence is preserved both for eager map entries and lazy range lookups.

While running the focused current-base family, the same parser area exposed missing CID range local variables. `parseCidCMap()` now restores the local code-space range collection before CID range parsing, so lazy CID ranges continue receiving the intended code-space context.

## Evidence

Red-first before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
```

Result: focused file failed. The new late-ToUnicode-`bfchar` case decoded stale `0123 4567` instead of `ABCD EFGH`; the same current-base run also exposed existing CID range local-variable warnings in this family.

Focused after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
```

Result: `1 test files / 278 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-cmap-tounicode-order-source-width-currentbase.php
```

The smoke emits `late_bfchar_override_applied=true`, `source_width_spans_applied=true`, `early_bfrange_decoy_excluded=true`, `false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders the Gutenberg paragraph `ABCD EFGH`.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 1 WordPress smoke scenario.
- Current focused source-width family is green at 278 assertions.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CID CMap `begincidrange`/late-`begincidchar` source order, zero-padded source-width fallback, predefined Identity-H/UCS2-H source widths, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID-map fallback, horizontal/vertical `TJ` adjustment gap handling, odd hex padding, repeated zero-padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined and object-valued `usecmap` inheritance, late malformed `usecmap` ordering, explicit low CID rows over malformed broad codespaces, zero-padded remapped CID ranges, high and large CID range expansion, lazy ToUnicode bfrange lookup, or Encoding CMap notdef rows.

The bounded behavior is specifically ToUnicode block source-order precedence between an earlier broad `beginbfrange` and later exact `beginbfchar` rows before source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping, styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
