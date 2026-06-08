# markerPDF CMap CID-Range Word-Spacing Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T182107Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T182107Z`
Base accepted HEAD: `74e2e1d508ba035b714146936835879271d84645`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.dictionary_output()` before Marker builds page/block/line/span output.
- The native PHP no-GPU fallback owns searchable-PDF CMap source-code segmentation, CIDFont width advances, and WordPress paragraph grouping when pdftext/PDFium, OCR/model workers, and external PDF tools are unavailable.
- For Type0 fonts with an Encoding CMap, PDF `Tw` word spacing must follow the source code's mapped CID when the CMap supplies CID rows/ranges, not raw source byte `0x20`.

## Behavior Added

`PdfTextExtractor::sourceKeyUsesWordSpacing()` now checks explicit CMap `cidRanges` before falling back to raw source code `0x20` when `wordSpacingUsesCidMap` is active. Direct `cidMap` entries still win first, and ordinary raw `0x20` fallback remains available for maps without explicit CID evidence.

The focused fixture covers both directions:

- raw source `<20>` mapped by `begincidrange` to CID 65 does not pick up `Tw` spacing;
- non-raw source `<30>` mapped by `begincidrange` to CID 32 remains eligible for CID-based `Tw` advance.

Visible text decoding is unchanged. The bounded delta is text advance/source-width spacing for Type0 CMap CID ranges.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapCidRangeWordSpacingSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses CMap cidrange CIDs rather than raw 0x20 for Type0 word-spacing advance on current base
PASS keeps Type0 CMap cidrange CID 32 eligible for word-spacing advance on current base

1 test files, 20 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-cidrange-word-spacing-source-width-currentbase.php
```

The smoke emitted `raw_source_0x20_uses_cidrange_nonspace=true`, `raw_source_0x20_false_word_gap_excluded=true`, `raw_source_0x20_word_spacing_not_applied_to_bbox=true`, `cid32_range_word_spacing_bbox_applied=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `3379 -> 3381`
- `wordpressScenarios`: `2751 -> 2752`
- Mapped upstream denominator unchanged; this is additive native PHP source-width behavior inside the existing CMap/font extraction cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CMap source-width fallback for zero-padded source codes, Identity-H/UCS2 source widths, `/DW` or partial metric misses, high/lazy CID ranges, UseCMap inheritance, malformed declared counts, missing/underdeclared code-space rows, notdef range ordering, ToUnicode bfrange cardinality, Type3 CMap spacing, vertical W2 metrics, stream-filter/CMap boundary validation, DCT/image metadata, xref repair, metadata, forms, annotations, OCR, or model execution.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping, styled-span bbox path, and WordPress smoke renderer. Live OCR/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
