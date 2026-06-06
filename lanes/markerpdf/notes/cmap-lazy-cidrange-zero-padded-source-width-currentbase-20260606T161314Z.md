# markerPDF CMap Lazy CIDRange Zero-Padded Source Width

Session: `port-dev-markerpdf-source-width-20260606T161314Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T161314Z`
Base accepted HEAD: `7058904a6976bbfea6cc05a241244898c074bd6a`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit delegates searchable-PDF text extraction through PDF text components that honor Type0 CMap source codes and descendant CIDFont metrics before Markdown/WordPress output. In this native no-GPU PHP lane, CMap parsing and source-width fallback must preserve text geometry without OCR, model workers, or external PDF tools.

This slice stays inside the existing CMap/source-width cluster. It covers the intersection of two already accepted behaviors: zero-padded source operands and large Encoding CMap `begincidrange` rows that remain lazy past the eager expansion cap. When the leading all-zero source code has no ToUnicode text mapping but the suffix source code resolves through a lazy CID range, the suffix CID must drive `/W` lookup instead of charging the padding as a visible glyph.

## Behavior Added

`PdfTextExtractor::zeroPaddedSourceKeysForFontWidths()` now treats all-zero exact source chunks as collapsible padding when they are width-only CMap entries and not ToUnicode text mappings. The suffix-CID collapse now resolves both exact `cidMap` rows and lazy `cidRanges`, and it refuses to collapse when the combined padded source code itself has a CID mapping.

`sourceKeyIsMappedForZeroPaddedWidth()` now recognizes lazy CID range metadata so suffix candidates past the expansion cap can be considered without materializing the full CMap.

The focused fixture uses:

- Encoding CMap `begincidrange <0000> <1FFF> 1000`, which leaves `<1000>` and later source codes in lazy range metadata after the 4096-entry eager cap.
- ToUnicode rows for suffixes `<1000>` through `<1007>`.
- Zero-padded text operands like `<00001000>`.
- CIDFont widths `/W [5096 5099 1000 5100 5103 250]`.

Before the source edit, the same fixture decoded visible text but produced `ABCDEFGH` and styled bboxes `[[0,0,72,12],[72,0,108,12]]`, proving the zero padding was being charged as width. After the fix, it imports `ABCD EFGH` with source-width bboxes `[[0,0,48,12],[48,0,60,12]]`.

## Evidence

Direct focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLazyCidRangeZeroPaddedSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses lazy Encoding CMap cidrange suffixes inside zero-padded source widths on current base
1 test files, 11 assertions, 0 failures
```

Adjacent CMap source-width regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 428 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCMapLazyCidRangeZeroPaddedSourceWidthCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCMapLazyCidRangeZeroPaddedSourceWidthCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-cmap-lazy-cidrange-zero-padded-source-width-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-lazy-cidrange-zero-padded-source-width-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-lazy-cidrange-zero-padded-source-width-currentbase.php
```

The smoke emits `lazy_cidrange_suffix_widths_applied=true`, `text_runs_preserved=true`, `zero_padding_width_excluded=true`, `false_merged_word_gap_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

No output.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback for direct ToUnicode rows, repeated zero padding, zero-padded small CID ranges, large lazy CID ranges without padding, lazy ToUnicode `bfrange` suffixes, high CID range expansion, sparse or delayed code-space sequence ranges, notdef ranges/chars, late CMap ordering, malformed/overflow CID ranges, Type3 CharProcs boundaries, stream filters, xref repair, metadata, annotations, forms, image/filter review, OCR, or model execution. The bounded behavior is specifically lazy Encoding CMap `cidrange` suffix CIDs for zero-padded source operands whose padding has no ToUnicode text.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, source tokenizer, CIDFont width parser, text-run grouping path, styled-span extraction, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.

Root harness: not run - isolated micro-slice.
