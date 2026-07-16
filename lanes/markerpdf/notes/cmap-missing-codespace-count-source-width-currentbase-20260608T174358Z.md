# markerPDF CMap Missing Codespace Count Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T174358Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T174358Z`

Base accepted HEAD: `00ea4d517c515ab21e88a62bfef7ac09185dceae`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through parser-backed pdftext/PDFium extraction before OCR or model fallback. In this no-GPU PHP lane, Type0 Encoding CMaps define source-code-to-CID mappings that CIDFont `/W` and `/DW` metrics use for text advance, styled-span bboxes, and WordPress paragraph gap decisions.

PDF CMap `begincodespacerange` blocks are count-prefixed. A present code-space block without an integer declared row count is malformed and must not authorize local CID rows to alter source-width grouping.

## Implementation

`PdfTextExtractor::cMapHasUnderdeclaredCodeSpaceRangeBlock()` now treats a missing `begincodespacerange` declared count as malformed. This keeps local ToUnicode or Encoding CMap mapping blocks fail-closed when the source-code boundary cannot be trusted, while PDFs with no local code-space block still use existing fallback paths.

The focused fixtures preserve valid `/ToUnicode` text for `Wide Thin`, then add an Encoding CMap with an uncounted code-space block and counted `begincidchar` or `begincidrange` decoys. Correct behavior ignores those local CID rows and uses raw source widths; accepting them collapses the WordPress paragraph to `WideThin`.

## Evidence

Red-first before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingCodespaceDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects Encoding CMap cidchar rows after missing codespace declared count before source-width fallback on current base
FAIL rejects Encoding CMap cidrange rows after missing codespace declared count before source-width fallback on current base

1 test files, 2 assertions, 2 failures
```

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingCodespaceDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects Encoding CMap cidchar rows after missing codespace declared count before source-width fallback on current base
PASS rejects Encoding CMap cidrange rows after missing codespace declared count before source-width fallback on current base

1 test files, 22 assertions, 0 failures
```

Adjacent CMap/source-width regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMissingCodespaceDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapUnderdeclaredEncodingCodespaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedCodespaceDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMissingDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
6 test files, 490 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-missing-codespace-count-source-width-currentbase.php
```

The smoke exits 0, renders one Gutenberg paragraph `Wide Thin`, and emits `missing_codespace_count_cidchar_rejected=true`, `missing_codespace_count_cidrange_rejected=true`, `source_width_word_gap_preserved=true`, `cid_decoy_widths_excluded=true`, `false_join_excluded=true`, `encoding_cmap_program_visible_text_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and handoff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCMapMissingCodespaceDeclaredCountSourceWidthCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCMapMissingCodespaceDeclaredCountSourceWidthCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-cmap-missing-codespace-count-source-width-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-missing-codespace-count-source-width-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0 with no output.

## Status Delta

- Added 2 focused PHP PASS cases.
- Added 22 focused assertions.
- Added 1 WordPress smoke scenario.
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior inside the already mapped CMap/font source-width boundary.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted missing declared counts on CID mapping blocks, malformed declared-count CID row-slot consumption, malformed/underdeclared codespace counts, negative/real/plus CID mapping block counts, Encoding reference tails, notdef rows, UseCMap inheritance, WMode handling, overlong/short source-width fallback, lazy CID ranges, sparse code-space ordering, malformed ToUnicode bfrange targets, stream-filter CMap boundaries, xref repair, annotations, forms, metadata, images, OCR, or model work.

The bounded behavior is specifically a missing integer declared count on a present Type0 Encoding CMap `begincodespacerange` block before local CID rows can affect source-width geometry.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap block parser, code-space guard, CIDFont width parser, text-position grouping, styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU direction.
