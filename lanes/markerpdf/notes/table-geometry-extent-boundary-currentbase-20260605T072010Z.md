# Table Geometry Extent Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T072010Z`
Base accepted HEAD: `da7b62c5099050bda765163fcb64c1d4fb8a0bc5`

## Source Truth

- Upstream markerPDF crops rendered page images around candidate table boxes before table-recognition/table-formatting handoff; recognized cells must be translated from page-image coordinates into table-crop-local coordinates before assignment.
- The locked tabled-pdf-style supplied result shape carries table, row, column, cell, and candidate `bbox` records. This PHP lane already supports endpoint arrays and named endpoint fields; this slice adds common serialized extent forms: `x/y/width/height`, `x0/y0/width/height`, and `left/top/width/height`.
- No OCR, Surya, Texify, Torch, Streamlit/FastAPI model worker, or live model benchmark path was executed. This is native supplied-boundary geometry behavior only.

## Behavior

`TableRecognizer`, `TableFormatter`, and `LayoutAnnotator` now convert extent-shaped bbox records to endpoint bboxes before existing normalization, table-crop translation, and WordPress conversion metadata handoff.

The new focused fixture verifies that:

- a page-image crop bbox shaped as `x/y/width/height` becomes `[72, 150, 312, 230]`;
- table, row, column, cell, and conflict-candidate geometry translate to crop-local coordinates with translation `[-72, -150]`;
- the original page-image source bbox remains available for review metadata;
- off-crop extent cells are excluded before Markdown/Gutenberg table emission;
- stale pdftext table lines are replaced by the supplied table.

## Red-First Evidence

Before the implementation change, the focused current-base test failed because associative extent bboxes were treated as endpoint lists:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryExtentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL translates page-image extent table geometry into crop-local assigned cells
Expected [72,150,312,230], Actual [72,80,240,150]
FAIL surfaces page-image extent table geometry through supplied WordPress conversion metadata
String does not contain '| Feature | Status |'
1 test files, 5 assertions, 2 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryExtentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS translates page-image extent table geometry into crop-local assigned cells
PASS surfaces page-image extent table geometry through supplied WordPress conversion metadata
1 test files, 42 assertions, 0 failures
```

Adjacent table/layout family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 952 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-extent-geometry-boundary-currentbase.php
```

The smoke emits `Feature/Status/Images/Ready` table Markdown, `source_cell_bbox=[82,155,162,170]`, `stale_pdftext_table_line_excluded=true`, `offcrop_extent_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP bbox normalization, table-recognition, layout-annotation, formatter, and supplied-document conversion components.

## Non-Overlap

This patch does not repeat accepted numeric-string bbox normalization, named endpoint bbox normalization, tabled-result bbox handoff, assigned-cell geometry, assigned-band geometry, page-image crop geometry, stale-polygon filtering, PageLabels, xref, image/filter, font, annotation, form, security, or runtime-preflight slices. The only new surface is extent-shaped supplied table/layout bbox records before crop-local assignment.

## Next Task

Continue with a non-overlapping markerPDF no-GPU parser/converter gap: searchable-PDF text/content-stream behavior, fonts/CMaps/widths, xref repair, metadata, outlines, annotations/forms/security preflight, image/filter metadata, page geometry, or supplied-boundary table/equation handoff behavior.
