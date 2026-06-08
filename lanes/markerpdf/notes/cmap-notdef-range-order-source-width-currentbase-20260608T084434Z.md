# CMap Notdef Range Order Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T084434Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T084434Z`
Accepted base: `efe757fea34410e917212cb2f88d964760b187d1`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the no-GPU markerPDF scope, searchable-PDF text extraction must preserve native PDF parser font/CMap behavior before any OCR/model handoff.

PDF Type0 font Encoding CMaps map content-stream source codes to descendant CIDs before CIDFont `/W` and `/DW` widths are applied. `beginnotdefrange` rows are fallback mappings; they must not override earlier explicit `begincidrange` rows, including earlier rows retained as lazy `cidRanges` because the range is too large for full eager expansion.

## Behavior

`PdfTextExtractor::parseCidRanges()` now uses the same non-overwrite guard for fallback ranges that `parseCidChars()` already used: when parsing a non-overwriting `beginnotdefrange`, a source key is skipped if an earlier direct `cidMap` row or an earlier delayed `cidRanges` row already maps that source key.

Before this patch, a later `beginnotdefrange <1800> <1807> 300` could write eager direct map entries over an earlier lazy `begincidrange <0000> <1FFF> 1000`. The valid CID widths for CIDs `7144..7151` were replaced by the fallback CID 300 width, causing a false WordPress word gap (`ABCD EFGH`) and collapsed styled span bboxes. After the fix, the explicit lazy range remains authoritative and the text imports as `ABCDEFGH`.

## Evidence

Red-first before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNotdefRangeOrderSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps earlier lazy CMap cidrange rows authoritative over later notdef ranges before source-width fallback on current base
Expected: array (0 => 'ABCDEFGH')
Actual: array (0 => 'ABCD EFGH')
1 test files, 1 assertions, 1 failures
```

Focused after source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNotdefRangeOrderSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps earlier lazy CMap cidrange rows authoritative over later notdef ranges before source-width fallback on current base
1 test files, 12 assertions, 0 failures
```

Adjacent notdef/source-width checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapNotdefRangeOrderSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
3 test files, 410 assertions, 0 failures
```

Broad CMap/font width family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(CMap|Font).*Width.*CurrentBaseTest\.php$' | sort)
59 test files, 1888 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-notdef-range-order-source-width-currentbase.php
```

The smoke exits 0 and emits `lazy_cidrange_beats_later_notdef_range=true`, `notdef_range_width_excluded=true`, `text_runs_preserved=true`, `false_word_gap_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta: `phpPass` `2999 -> 3000`; `wordpressScenarios` `2484 -> 2485`; mapped upstream denominator unchanged.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, ToUnicode metric-miss repair, partial metric-miss repair, TJ adjustment gaps, vertical `/W2`, high/large CID range expansion, sparse overflow CID ranges, notdef-range constant-CID semantics, later notdef-char non-overwrite after lazy ranges, array decoys, malformed CID target tails, declared-count parsing, source-key padding, UseCMap inheritance, stream-filter CMap boundaries, xref repair, images, annotations, forms, encryption preflight, OCR, model execution, or external PDF tools.

The bounded behavior is only `beginnotdefrange` fallback row ordering after an earlier lazy explicit `begincidrange` before source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text line/run/styled-span extraction, and WordPress smoke path. Live OCR, Surya/Texify/Torch, PDFium/PIL raster rendering, Python `pdftext`, external PDF binaries, and exact upstream model benchmark parity remain intentionally out of scope under the markerPDF no-GPU directive.
