# Table geometry short-extent boundary current-base slice

## Scope

This slice keeps markerPDF inside the native no-GPU table handoff path. It closes the supplied-boundary geometry case where layout/table-recognition sidecars encode top-left or center extents with compact `w` / `h` fields instead of `width` / `height`.

Source truth:

- Upstream `sddai/markerPDF` / Marker commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/tables/table.py::get_table_boxes` crops layout `Table` bboxes, passes table text lines to `tabled.inference.recognition.get_cells`, then hands recognized tables to `tabled.assignment.assign_rows_columns`.
- Locked `tabled-pdf` behavior keeps rows, columns, and cells as Bbox-shaped records for intersection assignment, overlap fallback, span handling, and markdown formatting.
- This PHP slice uses supplied layout and recognition dictionaries only. It does not run Surya, table recognition models, OCR, Python, pypdfium, PIL, external PDF tools, or GPU/model execution.

## Implementation

- `TableRecognizer` now accepts compact extent field sets for supplied table, row, column, cell, and OCR grid-border conflict geometry:
  - `x` / `y` / `w` / `h`
  - `x0` / `y0` / `w` / `h`
  - `left` / `top` / `w` / `h`
  - `cx` / `cy` / `w` / `h`
  - `center_x` / `center_y` / `w` / `h`
  - `x_center` / `y_center` / `w` / `h`
- The same compact top-left extent aliases are accepted by `LayoutAnnotator` and `TableFormatter` for supplied layout `Table` regions before the WordPress table-formatting handoff.
- Source review metadata now preserves the compact field-shape labels such as `bbox_xy_w_h_fields`, `bbox_left_top_w_h_fields`, and `bbox_cx_cy_w_h_fields`.

## Focused Evidence

Red-first current-base run before source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryShortExtentBoundaryCurrentBaseTest.php
```

Result: `1 test files, 0 assertions, 2 failures`; both tests failed with `Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.`

Post-fix focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryShortExtentBoundaryCurrentBaseTest.php
```

Result: `1 test files, 55 assertions, 0 failures`.

Adjacent table-geometry current-base family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'TableGeometry.*BoundaryCurrentBaseTest\.php$' | sort)
```

Result: `36 test files, 1477 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-table-short-extent-boundary-currentbase.php
```

Result: emitted `short_extent_aliases_translated_to_crop=true`, `stale_short_extent_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted long-form `width` / `height` extent geometry, center-plus-size geometry, center point plus nested `extent` / `size`, endpoint aliases, wrapped aliases, polygon aliases, serialized polygon fields, source-bbox fallback, normalized/page-image coordinate spaces, assigned crop/band filtering, table cursor/model execution, or OCR-grid conflict review behavior. The new bounded behavior is only compact `w` / `h` extent aliases flowing through supplied layout/table-recognition handoff.

## Dependency Closure

No new support component is needed. The patch reuses the existing native `LayoutAnnotator`, `TableFormatter`, `TableRecognizer`, and `SuppliedDocumentConverter` pipeline.

## Next

Continue with a non-overlapping markerPDF table/equation supplied-boundary or searchable-PDF parser slice, preferably a remaining geometry handoff not already covered by endpoint, polygon, wrapped, center, source-bbox, normalized, crop/band, or grid-border review tests.
