# markerPDF xref Prev action duplicate free rows current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T083959Z`

Session: `port-dev-markerpdf-xref-prev-chain-20260606T083959Z`

Accepted base: `1c1a61db384fdd8e13fd4a6e1d4e3e6abca49d7c`

## Source truth

Upstream markerPDF delegates searchable PDF extraction and annotation/link handling through native PDF readers before Markdown/WordPress output. In the no-GPU PHP lane, xref-selected page annotation action dictionaries are a parser dependency boundary: malformed incremental xref streams must not let stale or duplicate rows change which `/A` and `/AA` actions are promoted or reviewed.

PDF xref streams can carry sparse `/Index` ranges and incremental `/Prev` chains. The native text and metadata paths already preserve the first current-section row when malformed duplicate `/Index` ranges repeat an object. `PdfActionReviewExtractor` decoded xref streams independently and overwrote duplicate rows, so a later duplicate free row for action objects could erase the current URI action before WordPress link promotion.

## Change

- `PdfActionReviewExtractor::xrefStreamEntriesFromSection()` now keeps the first decoded current-section xref-stream row for an object and ignores later duplicate rows.
- `PdfXrefPrevChainActionReviewCurrentBaseTest.php` adds a red/green fixture where current action rows for objects `8` and `9` are followed by duplicate free rows in the same latest xref stream. WordPress link promotion now keeps the current URI and additional-action review metadata while excluding stale JavaScript/action text.
- `PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php` adds adjacent regression coverage proving current duplicate xref rows for EmbeddedFiles name-tree attachments stay selected before stale previous attachment rows.
- `wordpress-pdf-xref-prev-chain-action-review-currentbase.php` now smokes the duplicate-free-row action boundary and emits `duplicate_free_rows_ignored=true`.

## Evidence

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs action review xref-stream rows through Prev chain before WordPress link promotion
FAIL keeps first current action xref-stream rows before duplicate free rows
Expected: 'https://example.com/current-duplicate-action-free-row'
Actual: NULL
1 test files, 18 assertions, 1 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
2 test files, 557 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-currentbase.php
```

The smoke emits `current_uri_promoted=true`, `current_additional_action_reviewed=true`, `duplicate_free_rows_ignored=true`, `stale_prev_action_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted xref-stream `/Prev` stale direct-offset repair, classic xref rebuild, damaged `/Prev` fallback, indirect `/W`/`/Index`/`/Prev` helper resolution, same-generation text/metadata/EmbeddedFiles repair, duplicate attachment row selection, or action-chain safety review. The bounded behavior here is only current-section duplicate xref-stream free rows for action dictionaries before annotation/link review and WordPress span promotion.

## Dependency closure

No new support component is needed. This reuses the native PHP xref-stream parser, action reviewer, annotation/link extractors, and Markdown/WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering tools, and PDF action execution remain intentionally outside this no-GPU markerPDF scope.
