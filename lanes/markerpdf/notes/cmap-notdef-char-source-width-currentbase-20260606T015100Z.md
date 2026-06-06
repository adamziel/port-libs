# CMap Notdef Char Source Width Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T015100Z`

Accepted base: `990b499dc4e79bebbdeb8a6bdf28afd6ba5b9674`

## Source Behavior

At the native no-GPU searchable-PDF boundary, Type0 font Encoding CMaps map source codes to descendant CIDs before CIDFont `/W` and `/DW` metrics drive text advance grouping. `beginnotdefchar` rows are fallback mappings for unmapped source codes; they must not override an earlier valid `begincidrange`, including ranges kept lazy because they are larger than the eager expansion cap.

This mirrors the upstream markerPDF/pdftext boundary where CMap/font parsing happens before OCR/model stages and text geometry uses the font decoder's source-code segmentation rather than raw PDF bytes.

## Implementation

- `PdfTextExtractor::parseCidChars()` now checks prior lazy `cidRanges` when parsing non-overwriting CMap char blocks.
- A later `beginnotdefchar <1800> 300` no longer overrides a preceding large `begincidrange <0000> <1FFF> 1000`.
- The prior lazy range maps source `<1800>` to descendant CID `7144`, so `/W [7144 7147 1000 7148 7151 250]` drives WordPress text span geometry.

## Evidence

Red-first probe before the source edit:

```text
Ad hoc PHP probe on accepted base for large begincidrange plus later beginnotdefchar
Observed span bboxes: [[0,0,39,12],[39,0,51,12]]
Expected span bboxes: [[0,0,48,12],[48,0,60,12]]
```

Focused test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps lazy CMap cidrange rows authoritative over later notdef chars before source-width fallback on current base

1 test files, 10 assertions, 0 failures
```

Adjacent CMap source-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php
6 test files, 408 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-notdef-char-source-width-currentbase.php
```

The smoke emits `lazy_cidrange_beats_later_notdef_char=true`, `notdef_width_excluded=true`, `text_runs_preserved=true`, `nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss fallback, high CID range expansion, large lazy ToUnicode bfrange fallback, sparse multi-range code-space ranking, late cidchar/cidrange override ordering, explicit notdef range/char-only source widths, or bytewise CMap code-space membership. The bounded behavior is only non-overwriting `beginnotdefchar` rows after a prior lazy `begincidrange`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, source-code text segmentation, text-position grouping, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
