# Nested Saved rows_cols Order Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T151922Z`

Base: `6c4bc0c28ab0b14efaf267cac934e33e915db42f`

## Source Truth

- Upstream `sddai/markerPDF` crops rendered page images before handing table regions to tabled recognition and assignment.
- Locked tabled-pdf saved `TableResult` dictionaries carry row/column bands as `x1,x2,y1,y2` rectangles. Top-level saved results already used that default in this lane.
- `ExtractPageResult.rows_cols` can preserve those saved tabled `TableResult` containers nested beside page-level cells, so the same row/column band order default is required before table-crop localization.
- This slice uses supplied native artifacts only. It does not run Python, OCR, Surya, tabled models, GPU code, raster rendering, PDFium/PIL, or external PDF tools.

## Implementation

`TableRecognizer::withRowsColsGeometryMetadata()` now recognizes nested saved tabled `rows_cols` containers with `pnum`, `tnum`, positive `bbox`, positive `image_bbox`, and row/column records. When no explicit row/column order metadata is present, it applies the same tabled `x1_x2_y1_y2` default already used for top-level saved `TableResult` rows and columns.

This keeps nested saved row/column bands from being misread as xyxy, preserving right-column cells during assigned-cell formatting and keeping stale off-crop cells excluded before WordPress output.

## Red/Green Evidence

Red-first probe before source change:

```bash
php -r '...nested rows_cols saved TableResult probe...'
```

Result: nested row/column bands were localized as xyxy, `Status` and `Ready` were dropped from assigned cells, and Markdown rendered only one column:

```text
| Feature |
|---------|
| Images  |
```

Focused command after source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsSavedResultOrderBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 35 assertions, 0 failures
```

Adjacent rows/cols saved-result family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySavedTabledRowsColsOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultRowsColsAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySavedResultEnvelopeBoundaryCurrentBaseTest.php
```

Result:

```text
4 test files, 129 assertions, 0 failures
```

Full current-base table geometry family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
```

Result:

```text
67 test files, 2610 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-table-rows-cols-saved-result-order-currentbase.php
```

Result: exits 0 with `nested_saved_rows_cols_order_defaulted=true`, `right_column_cells_retained=true`, `offcrop_nested_saved_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, `executes_ocr=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Non-Overlap

This patch does not repeat accepted top-level saved tabled result order defaults, explicit `rows_cols` coordinate-order aliases, saved-result envelope basename selection, page-result rows/cols aliases, wrapped bbox order metadata, normalized/page-image coordinate-space localization, or assigned-cell crop/band filters. It covers only nested saved tabled `rows_cols` containers that carry saved-result markers but omit explicit row/column band order metadata.

## Dependency Closure

No new support component is needed. This slice reuses native table geometry alias normalization, supplied-document conversion, table-crop localization, assigned-cell crop filtering, span-grid review, and the WordPress smoke path. Live OCR, Surya/Texify/Torch, tabled model execution, page-pixel visual recognition, PDFium/PIL rendering, and exact upstream model parity remain intentionally out of scope under the no-GPU markerPDF directive.
