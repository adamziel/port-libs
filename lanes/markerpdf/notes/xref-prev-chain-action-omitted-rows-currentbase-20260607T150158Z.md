# markerPDF xref Prev-chain omitted action rows

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T150158Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF extraction through parser dependencies before OCR/model stages. Under the current no-GPU markerPDF scope, the native PHP lane owns xref selection, annotation/action review, and WordPress link promotion without executing PDF actions, Python models, OCR, PDFium rendering, or external PDF tools.

PDF incremental updates can append replacement annotation action dictionaries after an earlier xref section, then publish a latest xref stream with `/Prev`. If the latest rows select the current catalog, page tree, page, and annotation but omit the action objects referenced by `/A` and `/AA`, stale previous-section action rows must not win merely because `/Prev` is replayed.

## Behavior

`PdfActionReviewExtractor` now repairs omitted current update graph rows before merging previous `/Prev` entries. The repair starts from selected trailer references (`/Root`, `/Info`, `/Encrypt`) and follows only direct object references reachable through current in-window objects already selected by the latest xref section. If a referenced object is omitted from the latest rows but has a matching direct object body between the previous xref section and the current xref stream, the current direct object is selected before stale previous rows are inherited.

The focused fixture keeps stale Link annotation URI and JavaScript additional-action dictionaries in the previous xref table, appends current URI/mailto action dictionaries in the current update, and publishes latest xref-stream rows only for the catalog, page tree, page, font, and annotation (`/Index [1 5 7 1]`). WordPress link promotion now uses `https://example.com/current-omitted-action-row`, carries `mailto:current-omitted-action@example.test` as review metadata, and excludes stale previous URI/JavaScript action dictionaries.

## Evidence

Red baseline after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs action review xref-stream rows through Prev chain before WordPress link promotion
PASS keeps first current action xref-stream rows before duplicate free rows
FAIL repairs omitted current action rows before stale Prev action inheritance (lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php)
Values are not identical
Expected: 'https://example.com/current-omitted-action-row'
Actual: 'https://example.com/stale-omitted-action-row'
PASS selects latest classic xref-table action rows before post-xref decoys

1 test files, 51 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs action review xref-stream rows through Prev chain before WordPress link promotion
PASS keeps first current action xref-stream rows before duplicate free rows
PASS repairs omitted current action rows before stale Prev action inheritance
PASS selects latest classic xref-table action rows before post-xref decoys

1 test files, 63 assertions, 0 failures
```

Adjacent xref-prev/action/link family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChain*CurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 1376 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-currentbase.php
```

Emits `omitted_current_action_rows_repaired=true`, `current_uri_promoted=true`, `current_additional_action_reviewed=true`, `stale_prev_action_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref Prev-chain action row offset repair, duplicate free-row precedence, indirect `/Prev` helper resolution, classic post-xref action decoy exclusion, metadata/EmbeddedFiles omitted graph repair, same-generation text/metadata/attachment damaged offset repair, free annotation suppression, or page/resource lookup lineage.

The bounded behavior is specifically omitted current action dictionary rows in the latest xref-stream incremental update, repaired only when reachable from the selected current trailer/page/annotation graph before stale `/Prev` action inheritance.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF tokenizer/parser, xref table and xref-stream `/Prev` chain parser, Flate decoder, action review extractor, annotation/link promotion, Markdown postprocessor, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
