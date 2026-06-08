# markerPDF table page-result table_bboxes alias boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T071832Z`

Base accepted HEAD: `fcaa0b69bda960db88d81ee4e766e9ad2568afed`

## Source truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table conversion through `marker/tables/table.py::get_table_boxes()`: rendered page-image table crops produce `table_bboxes`, then `get_cells()`, `recognize_tables()`, `assign_rows_columns()`, and Markdown formatting consume geometry relative to those crops.
- Current no-GPU markerPDF scope owns the supplied-boundary handoff. It must preserve table crop geometry supplied by upstream/tabled sidecars without running Surya, tabled models, OCR, PDFium, PIL, Python, or external PDF tools.

## Implementation

- `SuppliedDocumentConverter::flattenRecognizedTablePageResults()` now accepts `table_bboxes`, `table_boxes`, `table_bounds`, and `table_regions` as page-result table crop aliases in addition to existing `bboxes`.
- Non-`bboxes` aliases are promoted to `table_bbox` with a source label such as `ExtractPageResult.table_bboxes`, so recognition geometry localizes against the supplied recognition crop before stale layout replacement boxes can be used as a fallback.
- Page-result review metadata now records the selected table bbox source alias.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultTableBboxesAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL propagates ExtractPageResult table_bboxes aliases before table crop localization
String does not contain '| Feature | Status |'
1 test files, 2 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultTableBboxesAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS propagates ExtractPageResult table_bboxes aliases before table crop localization
1 test files, 28 assertions, 0 failures
```

Adjacent family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResult*CurrentBaseTest.php
8 test files, 261 assertions, 0 failures
```

Shared table geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
57 test files, 2257 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-page-result-table-bboxes-alias-boundary-currentbase.php
```

The smoke exits 0 and reports `table_bboxes_alias_propagated=true`, `offcrop_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
No syntax errors detected in lanes/markerpdf/src/SuppliedDocumentConverter.php

php -l lanes/markerpdf/tests/TableGeometryPageResultTableBboxesAliasBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryPageResultTableBboxesAliasBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-page-result-table-bboxes-alias-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-page-result-table-bboxes-alias-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0 with no output.

## Dependency closure

No new support component is needed. This slice reuses the native PHP supplied-document converter, upstream page-result flattener, table recognizer crop-local geometry localization, table formatter, and WordPress smoke path. Live OCR, Surya/Texify/Torch, tabled model inference, Python/PDFium/PIL rendering, and full upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-overlap

This does not repeat accepted flat recognized-table coordinate-order support, page-result `bboxes` coordinate-space/order propagation, shared `image_bbox` normalization, `table_imgs` highres crop metadata, rows/cols alias propagation, crop polygon handling, scalar spans, band ordering, detector source boundaries, or page-result table image slices. The bounded behavior is only page-result table crop aliases named `table_bboxes` and equivalent table-box list aliases surviving flattening before recognition crop localization.
