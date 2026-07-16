# Table Geometry Scalar Span Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T124825Z`

Accepted base: `c1cf1f37714011b48942dddb280e21fdc933c11e`

## Source Truth

- Current Surya table-recognition schema exposes `TableCell` fields as scalar `row_id`, `col_id`, `rowspan`, and `colspan`; see `surya/table_rec/schema.py` upstream: https://raw.githubusercontent.com/VikParuchuri/surya/master/surya/table_rec/schema.py
- Existing PHP markerPDF table code already handled the older assigned `row_ids`/`col_ids` array shape. This slice ports the scalar current-upstream handoff shape into that same supplied-boundary path.
- No GPU/model/OCR execution was used. The fixture supplies rows, columns, and cells directly and verifies native PHP table geometry conversion/review behavior only.

## Behavior

`TableRecognizer` now accepts cells with scalar `row_id`/`col_id` anchors and optional `rowspan`/`colspan`, expands them to canonical `row_ids`/`col_ids` using the ordered active row/column bands, and then runs the existing crop and band boundary filters.

This preserves non-contiguous detector ids such as columns `[5, 7, 9]` for a `colspan: 3` cell and rows `[20, 30]` for a `rowspan: 2` cell. Off-image scalar cells are still excluded by the crop boundary before WordPress Markdown/table review.

## Red/Green Evidence

Red run before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryScalarSpanBoundaryCurrentBaseTest.php
```

Result: `1 test files, 10 assertions, 2 failures`; scalar `colspan` remained `[5]` instead of expanding to `[5, 7, 9]`.

Green focused run after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryScalarSpanBoundaryCurrentBaseTest.php
```

Result: `1 test files, 38 assertions, 0 failures`.

Focused regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryScalarSpanBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php
```

Result: `4 test files, 581 assertions, 0 failures`.

## WordPress Smoke

Added:

```bash
php lanes/markerpdf/examples/wordpress-table-scalar-span-boundary-currentbase.php
```

The smoke emits `expanded_inventory_col_ids=[5,7,9]`, `expanded_media_row_ids=[20,30]`, `inventory_colspan=3`, `media_rowspan=2`, `offcrop_scalar_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the existing array-assigned table spans, crop/band filtering, detector crop, polygon, bbox normalization, or named-destination/xref/security slices. It only adds the scalar current-upstream cell assignment shape before the already accepted boundary machinery.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `TableRecognizer`, `SuppliedDocumentConverter`, existing row/column geometry boundary helpers, and WordPress table review metadata. Remaining model/OCR parity is intentionally out of no-GPU scope.
