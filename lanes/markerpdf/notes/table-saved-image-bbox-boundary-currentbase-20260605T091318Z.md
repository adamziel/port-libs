# Table Saved Image Bbox Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T091318Z`

## Source Truth

Upstream `tabled.extract.extract_tables()` constructs an `ExtractPageResult` with a full page `image_bbox` for each high-resolution page and table bboxes for each detected table. Saved result JSON persists each table with `rows`, `cols`, `cells`, top-level `bbox`, and `image_bbox`.

This PHP slice covers the supplied-boundary handoff for saved tabled-pdf results: if a caller reloads saved recognition JSON without a separate `width`/`height` crop sidecar, the top-level table `bbox` extent is sufficient to recover the cropped table image size for row/column/cell boundary filtering.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL derives saved tabled result table image size from top-level bbox extent (lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php)
Table image sizes must include positive width and height.

1 test files, 0 assertions, 1 failures
```

## Implementation

`TableRecognizer::formatRecognizedTables()` now resolves a recognition image size for each table before localization and assignment. Explicit `width`/`height` remains authoritative. When it is absent, `table_bbox`/`table_crop_bbox`/top-level `bbox` extent supplies the cropped table image size, and the enriched size is reused for coordinate localization, assigned-cell crop filtering, active-band filtering, and row/column assignment.

Coordinate-space review metadata records `image_size_source: table_crop_bbox_extent` so WordPress import diagnostics can distinguish this saved-result fallback from explicit sidecar sizes.

## Verification

Focused post-change command:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS derives saved tabled result table image size from top-level bbox extent

1 test files, 23 assertions, 0 failures
```

Full verification for this handoff is recorded in the worker final response.

Additional focused family and smoke checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-saved-image-bbox-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-saved-image-bbox-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 1769 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-saved-image-bbox-boundary-currentbase.php
emits image_size_source=table_crop_bbox_extent, table_crop_size=240x80, assigned_table_texts=[Feature, Status, Images, Ready], executes_python_or_models=false, executes_external_pdf_tools=false

git diff --check -- lanes/markerpdf
clean
```

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP bbox parsing, table crop bbox normalization, coordinate localization, and assignment filtering. It does not run live OCR, Surya, tabled/Texify models, Python, external PDF tools, or GPU/model execution.

## Non-Overlap

This does not repeat existing table crop clipping, record coordinate-space localization, extent bbox parsing, table conflict translation, assigned band filtering, or explicit rendered-image-size handling. The new behavior is limited to persisted saved tabled-pdf result JSON where top-level table `bbox` provides the missing crop image-size sidecar.
