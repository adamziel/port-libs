# markerpdf classic xref rebuild free-object map current-base

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T123513Z`
- Accepted base: `f232ea710bb84947bacd91a25f6a2c27190164fc`
- Native no-GPU scope only: searchable-PDF xref repair, annotation filtering, and WordPress import review metadata.

## Source truth

PDF incremental updates can append a later classic xref table with `/Prev` while the final `startxref` operand is stale or damaged. The main markerPDF text/metadata extractors already rebuild classic xref selection to the latest valid top-level classic table before the final `startxref` token while keeping xref-stream failures fail-closed. This slice applies that same classic rebuild boundary to the lightweight `PdfXrefFreeObjectMap` used by annotation/link review, so current free rows suppress stale annotation objects before WordPress import.

## Behavior

- `PdfXrefFreeObjectMap::freeObjectNumbers()` now selects a repaired classic xref table when the latest `startxref` offset points outside the file or misses a valid section.
- Rebuild fallback is classic-table-only. If the declared `startxref` points at an xref-stream object, the free map keeps that offset and returns no rows on decode failure instead of falling back to stale classic tables.
- Added a focused fixture where the current classic xref table marks stale link annotation object `7 0 R` free, but the final `startxref` is `999999`. The free map now reports object `7` free and the link/review annotation extractors suppress the stale URI.
- Added a WordPress smoke that emits current paragraph content only and records no Python/model or external PDF-tool execution.

## Evidence

Red-first before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rebuilds damaged classic startxref for the free-object map before annotation review
Damaged classic startxref rebuild must preserve current free rows.
1 test files, 3 assertions, 1 failures
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
1 test files, 11 assertions, 0 failures
```

Adjacent free-object/xref rebuild family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 107 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-currentbase.php
reports free_object_map_rebuilt_to_current_classic_xref=true, suppresses_stale_link_annotation=true, suppresses_stale_review_annotation=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency closure

No new support component is needed. This reuses native PHP xref table/stream parsing already present in `PdfXrefFreeObjectMap`; no OCR, Surya, Texify, Torch, model worker, live service, pypdfium, or external PDF tool is introduced.

## Non-overlap

This does not change DCT DecodeParms, xref-stream filter decoding, object-stream member extraction, plus-signed subsection parsing, or the main text/metadata classic rebuild path. It is limited to free-object map selection used by annotation/link filtering.
