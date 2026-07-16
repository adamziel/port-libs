# markerpdf xref Prev chain compressed action rows current-base

Date: 2026-06-08 UTC

Base accepted HEAD: `6c29be4bda70f43b52fe8fb02b6dc807643e8db3`

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T082318Z`

## Behavior

This patch keeps markerPDF in the native no-GPU scope and extends the action-review xref repair path for incremental updates:

- Latest xref stream has `/Prev` and selects the current catalog/page/annotation graph.
- The current annotation points to action objects that are stored inside a current `/ObjStm`.
- The latest xref stream selects the current object-stream carrier but omits the type-2 rows for those action objects.
- The previous xref section has stale direct URI/JavaScript rows for the same action object numbers.

`PdfActionReviewExtractor` now graph-walks current `Root`/`Info`/`Encrypt` references before previous-section inheritance and adds unique current object-stream member xref rows for referenced objects. That prevents stale `/Prev` direct action rows from replacing current compressed action dictionaries during WordPress link promotion and additional-action review.

## Non-overlap

This does not repeat the existing current-base action-review `/Prev` coverage for:

- damaged explicit direct action offsets;
- omitted current direct action rows;
- indirect `/Prev` helper resolution;
- non-incremental object-stream action selection.

The new coverage is specifically omitted type-2 rows for current compressed action objects with stale previous direct rows.

## Evidence

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedActionRowsCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS prefers current compressed action objects omitted from latest xref rows over stale Prev rows

1 test files, 25 assertions, 0 failures
```

Adjacent action-review/object-stream regression:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedActionRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php
```

Result:

```text
4 test files, 132 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-compressed-action-rows-currentbase.php
```

Result: exits 0 and reports `omitted_compressed_action_rows_repaired=true`, `current_uri_promoted=true`, `current_additional_action_reviewed=true`, `stale_prev_action_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object-stream decoder, xref-stream parser, action review parser, and WordPress link-promotion path. No OCR, Surya/Texify/Torch, raster model execution, live service, external PDF tool, decryption/password validation, or PDF action execution is introduced.

## Next

Continue with non-overlapping native markerPDF parser behavior around xref repair, stream filters, font/CMap boundaries, annotations/forms, page geometry, metadata, outlines, attachments, image/filter metadata, and supplied-boundary table/equation handoffs.
