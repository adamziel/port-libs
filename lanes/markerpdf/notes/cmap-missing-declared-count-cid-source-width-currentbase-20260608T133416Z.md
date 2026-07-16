# markerPDF CMap Missing Declared-Count CID Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T133416Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T133416Z`

Base accepted HEAD: `ab39c48b2a82ff9622403db018d37fcff9180477`

## Source Truth

Pinned upstream markerPDF keeps searchable-PDF text extraction on the PDF parser/font boundary before OCR or model fallback. In this no-GPU PHP lane, Type0 Encoding CMaps define source-code-to-CID mappings that CIDFont `/W` and `/DW` metrics use for text advance, styled-span bboxes, and WordPress paragraph gap decisions.

PDF CMap `begincidchar`, `begincidrange`, `beginnotdefchar`, and `beginnotdefrange` blocks are count-prefixed mapping blocks. A block without an integer declared row count is malformed and must not override valid counted CID mappings before source-width fallback.

## Implementation

`PdfTextExtractor::cMapCidMappingBlocks()` now matches the existing ToUnicode mapping-block gate: if the scanner finds a CID mapping begin/end pair but no integer declared count before the begin operator, the block is skipped before `parseCidChars()` or `parseCidRanges()` can mutate the CID map.

The focused fixtures keep an earlier valid counted `begincidchar` block mapping `<10>` through `<13>` to narrow CIDs 60..63. They then append malformed uncounted `begincidchar` or `begincidrange` decoys mapping the same sources to wide CIDs 40..43. Correct behavior preserves `Wide Wide` and 12pt source-width bboxes; accepting the malformed decoys collapses the text to `WideWide` with 48pt spans.

## Evidence

Red-first before the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingDeclaredCountCidSourceWidthCurrentBaseTest.php
```

Result: `1 test files, 2 assertions, 2 failures`; both cases extracted `WideWide` instead of `Wide Wide`.

Focused after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingDeclaredCountCidSourceWidthCurrentBaseTest.php
```

Result: `1 test files, 22 assertions, 0 failures`.

Adjacent declared-count/CID source-width family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapRealDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php
```

Result: `6 test files, 130 assertions, 0 failures`.

Broader CMap/font source-width focused family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
```

Result: `45 test files, 973 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-cmap-missing-declared-count-cid-source-width-currentbase.php
```

The smoke exits 0 and emits `missing_count_cidchar_block_rejected=true`, `missing_count_cidrange_block_rejected=true`, `source_width_word_gap_preserved=true`, `wide_decoy_widths_excluded=true`, `false_join_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders the Gutenberg paragraph `Wide Wide`.

## Status Delta

- Added 2 focused PHP PASS cases.
- Added 22 focused assertions.
- Added 1 WordPress smoke scenario.
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font source-width boundary.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed declared-count CID row-slot consumption, plus/negative/real declared-count handling, array-wrapped CID decoys, malformed CID target tails, underdeclared Encoding codespaces, Encoding reference tails, late CID char/range ordering, notdef rows, delayed/multi-range codespaces, invalid/overflow CID ranges, ToUnicode row-count/filter boundaries, ToUnicode source-order precedence, source-key zero padding, literal ToUnicode targets, lazy bfrange/CID range expansion, Type3 CMap word-spacing boundaries, stream filters, xref repair, annotations, forms, metadata, images, OCR, or model work.

The bounded behavior is specifically missing integer declared counts on Type0 Encoding CMap CID mapping blocks before source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, token-aware CMap operator scanner, CID CMap row parsers, CIDFont width parser, text-position grouping, styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU direction.
