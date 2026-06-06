# Table Page-Result Bbox Coordinate Space Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T213316Z`

Accepted base: `82417ef603248e0de68523a91f6e2f08dde5f687`

## Source Truth

- Locked markerPDF table conversion crops each high-resolution page table bbox before tabled assignment and Markdown formatting.
- The supplied no-GPU PHP lane consumes `tabled.schema.ExtractPageResult`-style envelopes as saved recognition geometry. Those envelopes can carry page-level `bboxes` table crop rectangles plus field-specific metadata such as `bboxes_coordinate_space`.
- This slice stays inside supplied-boundary table geometry. It does not run Surya, tabled/Texify/Torch models, OCR, Python, PDFium/PIL rasterization, Streamlit/FastAPI workers, or external PDF tools.

## Behavior

- `SuppliedDocumentConverter` now propagates page-level table-bbox metadata from `ExtractPageResult` envelopes onto the flattened table record's singular `bbox_*` metadata before recognition formatting.
- `TableRecognizer` now accepts plural `bboxes_*` / `table_bboxes_*` coordinate-space aliases when a flattened saved table record stores its crop rectangle in the top-level `bbox` field.
- The new focused test covers both public boundaries:
  - a direct flattened saved table record whose `bbox` is normalized page-image geometry and whose coordinate-space label is only `bboxes_coordinate_space`;
  - a WordPress supplied conversion using an `ExtractPageResult` envelope where stale page-result row/column cells are filtered and the Gutenberg table renders `Feature | Status` / `Images | Ready`.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultBboxCoordinateSpaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL propagates page result bboxes coordinate space before table crop localization (lanes/markerpdf/tests/TableGeometryPageResultBboxCoordinateSpaceBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'normalized_page_image'
Actual: NULL

1 test files, 12 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultBboxCoordinateSpaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses page result bboxes coordinate space for flattened table crop localization
PASS propagates page result bboxes coordinate space before table crop localization

1 test files, 57 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPageResultBboxCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultSharedImageBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryGenericCropBboxCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedCropBboxBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 239 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'TableGeometry*CurrentBaseTest.php' | sort)
Focused test run: 44 selected test files (root lock skipped)
44 test files, 1820 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-page-result-bbox-coordinate-space-boundary-currentbase.php
```

The smoke reports `page_result_bboxes_coordinate_space_localized=true`, `wordpress_table_rendered=true`, `stale_page_result_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses native supplied PDF dictionary/page conversion, existing table geometry localization, and deterministic saved recognition inputs. GPU/model/OCR parity remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted normalized crop `table_bbox_coordinate_space=normalized_page_image`, generic top-level `coordinate_space`, page-result coordinate-order propagation, shared `image_bbox` normalization, crop polygon stale-bbox precedence, row/column/cell alias normalization, or direct page-image/crop-local table assignment slices. The bounded behavior is specifically page-result plural `bboxes_*` table crop metadata surviving flattening and public recognizer localization.
