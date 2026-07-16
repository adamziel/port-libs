# markerPDF xref Prev chain omitted outline rows current-base

Date: 2026-06-05 UTC
Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T222157Z`
Base accepted HEAD: `59b7ab61f9bf14128c46b8fe48f28d13d62b387f`

## Source Truth

Upstream markerPDF routes searchable-PDF text and document navigation through PDF parser dependencies before OCR/model fallback. In the native no-GPU PHP lane, xref `/Prev` chain selection must keep current incremental outline/navigation objects authoritative before WordPress TOC import, while older `/Prev` rows remain available only for objects not replaced by the current revision.

Some incremental PDFs append current same-generation outline, page, name-tree, action, and content objects but keep a sparse latest xref stream with only the current catalog row plus `/Root` and `/Prev`. The current catalog row is explicit and valid, but the current objects reachable from it are omitted from the latest row set. Those reachable current objects must seed repair before stale previous-section rows are inherited.

## Behavior

`PdfOutlineExtractor` now repairs omitted current direct rows reachable from the latest trailer `/Root` graph even when the root row itself is already explicitly present in the latest xref stream. Valid current in-use rows seed nested traversal; explicit free rows or stale-offset rows are not treated as traversal seeds.

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now use the same explicit-current-row traversal seed in their existing omitted-row graph repair. This keeps visible page text, XMP/Info/catalog metadata, EmbeddedFiles, attachment summaries, and outline navigation aligned on the current revision.

## Evidence

Red-first before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainOmittedRowsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs omitted current outline graph rows before stale xref Prev navigation rows (lanes/markerpdf/tests/PdfOutlineXrefPrevChainOmittedRowsCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current Omitted Outline Start',
  1 => 'Current Omitted Outline Review',
)
Actual: array (
  0 => 'Previous Omitted Outline',
)

1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainOmittedRowsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs omitted current outline graph rows before stale xref Prev navigation rows

1 test files, 15 assertions, 0 failures
```

Adjacent xref/outline family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainOmittedRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineXrefStreamPrevChainOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 536 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-omitted-outline-rows-currentbase.php
current_outline_rows_selected=true
current_outline_actions_reviewed=true
current_page_text_selected=true
omitted_current_outline_rows_repaired=true
stale_prev_outline_excluded=true
stale_prev_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted omitted-row repair where the latest sparse xref stream omits the root row entirely, damaged explicit-offset repair, stale explicit-offset repair, wrong-current-object offset repair, free-row suppression, indirect `/Prev` helpers, compressed `/Prev` helpers, object-stream carrier repair, hybrid xref precedence, outline post-xref owner selection, or stream-filter/font/CMap behavior.

The bounded behavior here is specifically traversal from a valid explicit current root row into omitted current same-generation graph objects before stale `/Prev` rows can satisfy outline/navigation and page-text imports.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanners, xref table/stream `/Prev` chain walker, Flate xref-stream decoding, direct object graph traversal, text extraction, outline/navigation review, metadata, embedded-file, and attachment-summary paths. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
