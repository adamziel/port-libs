# markerPDF CMap cidchar order source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T135504Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T135504Z`

Base accepted HEAD: `c3f58029a60723af2704c75d84b8e5f448630194`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through pdftext/PDF parser font machinery before Marker builds page text and Markdown. In the current no-GPU PHP lane, the native fallback owns the PDF parser boundary for CMaps, font encodings, and descendant CIDFont widths.

The bounded PDF parser behavior here is CMap mapping order. Type0 `/Encoding` CMaps map source character codes to descendant CIDs, and CIDFont `/W`, `/DW`, `/W2`, `/DW2`, or `/CIDSet` metrics are keyed by those descendant CIDs. When a CMap first declares a broad `begincidrange` and then supplies later `begincidchar` overrides for the same source codes, the later exact rows must drive source-width grouping before WordPress paragraph gap decisions.

## Implementation

`PdfTextExtractor::parseCidCMap()` now walks `beginnotdefchar`, `beginnotdefrange`, `begincidchar`, and `begincidrange` blocks in stream order via `cMapCidMappingBlocks()`. Notdef mappings remain non-overwriting, while normal CID mappings preserve their existing overwrite behavior. This keeps direct CID rows and lazy CID range metadata aligned with the actual CMap program order.

The focused fixture uses:

- an Encoding CMap with `<20> <27> 100` first;
- later exact `begincidchar` rows remapping `<20>` through `<23>` to CIDs `300..303`;
- a descendant CIDFont `/W [100 107 1000 300 303 250]`;
- two source chunks positioned so the later cidchar overrides produce `ABCD EFGH`, while the stale range-only path joins `ABCDEFGH`.

## Evidence

Red-first before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
```

Result: `1 test files / 1 assertions / 1 failure`, expected `["ABCD EFGH"]` but got `["ABCDEFGH"]`.

Focused after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
```

Result: `1 test files / 10 assertions / 0 failures`.

Adjacent CMap/source-width family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
```

Result: `3 test files / 276 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-cmap-cidchar-order-source-width-currentbase.php
```

The smoke emits `later_cidchar_override_applied=true`, `cidchar_override_span_widths_applied=true`, `early_cidrange_false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders the Gutenberg paragraph `ABCD EFGH`.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 10 focused assertions.
- Added 1 mapped manifest behavior.
- Added 1 WordPress smoke scenario.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H source widths, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID-map fallback, horizontal/vertical `TJ` adjustment gap handling, odd hex padding, repeated zero-padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined and object-valued `usecmap` inheritance, late malformed `usecmap` ordering, explicit low CID rows over malformed broad codespaces, zero-padded remapped CID ranges, high and large CID range expansion, lazy ToUnicode bfrange lookup, or Encoding CMap notdef rows.

The bounded behavior is specifically stream-order precedence between normal `begincidrange` and later `begincidchar` rows before source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping, styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
