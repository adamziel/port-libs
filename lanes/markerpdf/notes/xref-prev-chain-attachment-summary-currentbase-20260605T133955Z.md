# markerPDF xref Prev chain attachment summary current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T133955Z`
Base: `390fd9d5a6e0ca911e658e0a91d7a37d894b97ef`

## Source Truth

Upstream markerPDF routes searchable-PDF import through parser-backed PDF object loading before OCR/model fallback. Under the current no-GPU lane scope, this PHP port owns native xref repair, trailer chaining, metadata, text, and attachment preflight behavior without launching OCR, Surya, Texify, Torch, PDFium, or external PDF tools.

Classic xref-table incremental updates may point their current in-use rows at damaged, stale, or wrong-owner offsets while a later direct object definition for the same object and generation exists between the previous xref section and the current xref table. The current update object should win over stale `/Prev` rows for attachment summaries just as it already does for text, metadata, and embedded-file extraction.

## Behavior

`PdfAttachmentExtractor::attachmentSummary()` now reuses the same direct-object window repair used by the xref-table object resolver:

- damaged explicit offsets in the current classic xref table are replaced by the latest same object/generation direct definition between the previous xref section and the current xref table;
- stale explicit offsets that still point at an older object body no longer win over a current update object;
- direct numeric `/Prev` helper references such as `/Prev 30 0 R` are resolved from direct object definitions before the current xref table, so later same-number decoys after the xref table cannot redirect the chain.

The repair is limited to in-use classic xref-table rows in the current update window. Free rows and unrelated generations remain untouched.

## Evidence

Red probes before source repair:

```text
classic xref-table damaged current rows:
attachmentSummary(...) => attachment_count=0

classic xref-table damaged /Prev with stale current rows:
attachmentSummary(...) => filenames=["stale-damaged-prev-table.xml"]

classic xref-table direct /Prev helper before post-table decoy:
attachmentSummary(...) => attachment_count=0
```

Focused baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 411 assertions, 0 failures
```

Focused green after source repair and assertion expansion:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs same-generation current update objects when classic xref Prev rows have damaged explicit offsets
PASS repairs latest classic xref-table stale rows after damaged Prev pointer recovery
PASS repairs classic xref-table direct Prev helper before post-table same-number decoys
1 test files, 435 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke emits:

- `classic_table_same_generation_attachment_preflight_selected=true`
- `classic_table_same_generation_attachment_preflight_no_runtime_execution=true`
- `classic_table_damaged_prev_attachment_preflight_selected=true`
- `classic_table_damaged_prev_attachment_preflight_no_runtime_execution=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness status: not run - isolated micro-slice.

Status delta:

- Behavior tests: unchanged at `1876` because this patch expands assertions inside existing focused cases rather than adding a new PASS case.
- Focused assertions in `PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`: `411 -> 435`.

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` current-row repair, indirect `/W` and `/Index` helper resolution, compressed `/Prev` helper recovery, object-stream member recovery, classic rebuild from older `startxref`, generation-exact metadata and EmbeddedFiles selection, free-row suppression, trailer root fallback blocking, outline repair, stream-filter recovery, OCR/model execution, or table/equation handoffs.

The bounded behavior here is attachment-summary preflight for classic xref-table `/Prev` incremental updates whose current same-generation rows need direct-object repair or whose `/Prev` offset is itself supplied by a direct numeric helper object.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, classic xref-table parser, xref `/Prev` chain resolver, embedded-file extractor, and attachment-summary preflight path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
