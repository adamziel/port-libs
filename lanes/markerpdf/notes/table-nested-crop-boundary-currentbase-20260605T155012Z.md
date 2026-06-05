# markerPDF table nested crop boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T155012Z`

Base accepted HEAD: `2069ed7e1febba5c2afce1b99c380343613b723c`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The native no-GPU table handoff mirrors `marker/tables/table.py::get_table_boxes()` plus tabled assignment: page-image table boxes are cropped before row, column, and cell assignment. Saved recognition sidecars can preserve that crop as a nested table-image/crop record instead of a top-level `bbox`; the PHP recognizer must still translate page-image rows, columns, cells, and OCR conflict bboxes into table-crop coordinates before WordPress table review/Markdown output.

No live OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, Streamlit/FastAPI model worker, external PDF tool, or online service was executed.

## Implementation

- `TableRecognizer` now derives table crop candidates from nested `table_image`, `table_crop`, `crop`, `crop_image`, `table_region`, and `table_box` records with nested `highres_bbox`, `crop_bbox`, `source_bbox`, `bbox`, or polygon-style geometry.
- The derived recognition image-size handoff preserves the original nested bbox source label, so review metadata reports `table_image.highres_bbox` instead of collapsing it to the synthetic `table_bbox`.
- Added focused tests for direct recognizer use and supplied WordPress conversion metadata.
- Added `wordpress-table-geometry-nested-crop-boundary-currentbase.php` as a WordPress-facing smoke.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL derives page-image crop boundary from nested saved table image metadata
Values are not identical
Expected: 'translated_to_table_crop'
Actual: 'missing_table_crop_bbox'
PASS surfaces nested crop table geometry through supplied WordPress conversion metadata

1 test files, 19 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS derives page-image crop boundary from nested saved table image metadata
PASS surfaces nested crop table geometry through supplied WordPress conversion metadata

1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 2124 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-geometry-nested-crop-boundary-currentbase.php
```

The smoke emitted `direct_nested_crop_source=table_image.highres_bbox`, `direct_table_crop_size={"width":240,"height":80}`, `assigned_texts=["Feature","Status","Images","Ready"]`, `excluded_stale_pdftext_table_line=true`, `excluded_stale_nested_sidecar_cells=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`. The focused test also validates direct `crop.bbox` sidecars with no layout-provided `table_bbox`.

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-geometry-nested-crop-boundary-currentbase.php
```

All lint commands reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted table crop-local assignment, saved top-level table `bbox`/polygon boundaries, page-image/normalized coordinate localization, polygon alias parsing, extent/named-bbox normalization, mixed coordinate counters, source field-shape review, assigned crop/band filtering, detector crop cells, or OCR grid-border review. The new behavior is specifically nested saved crop metadata used as the current table crop boundary before translation/assignment.

## Dependency closure

No new support component is needed. This reuses the native PHP supplied-document converter, table crop planner, geometry normalizer, recognizer assignment, span-grid review, Markdown table formatter, and WordPress smoke path. Full upstream model parity remains intentionally out of scope for this no-GPU markerPDF lane.
