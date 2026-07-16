# markerPDF CMap notdefchar source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T105420Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T105420Z`
Base accepted HEAD: `82babcc28b3524f3f387fea16306591bd14fd892`

## Scope

This slice stays inside the native searchable-PDF CMap/font-width boundary. PDF CMaps can provide `beginnotdefchar` source-to-CID rows as single-character fallbacks, and the same source rows need to feed descendant CIDFont `/W` widths before WordPress paragraph grouping and styled-span bbox construction.

The focused fixture adds an Encoding CMap with eight `beginnotdefchar` rows for source bytes `<20>` through `<27>`, a ToUnicode map for `ABCDEFGH`, and descendant `/W` metrics where CIDs 100-103 are wide and 104-107 are narrow. It verifies text lines, runs, plain text, naive text, span texts, span bboxes, line bbox, false-join exclusion, and NUL-byte exclusion.

## Evidence

Pre-edit focused baseline:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 236 assertions, 0 failures
```

Focused run after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 246 assertions, 0 failures
PASS uses Encoding CMap notdef chars before source-width fallback on current base
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke exits 0 and emits `notdef_char_source_widths_applied=true`, `notdef_char_runs_preserved=true`, `notdef_char_span_widths=true`, `notdef_char_false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object parser, stream decoder, CMap operator parsing, Type0 Encoding CMap CID map handling, descendant CIDFont `/W` metrics, source-width text advance path, and WordPress smoke path. GPU/model/OCR, pdftext/PDFium parity, Surya/Texify/Torch, live services, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss ToUnicode fallback, partial metric-miss repair, horizontal/vertical `TJ` gaps, odd hex padding, one-byte codespace padding, repeated zero padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined `usecmap`, explicit low CID rows, zero-padded remapped CID ranges, broad ToUnicode codespace recovery, Encoding CMap notdef ranges, or high CID range expansion. The new boundary is specifically `beginnotdefchar` rows feeding source-width fallback and styled-span geometry.
