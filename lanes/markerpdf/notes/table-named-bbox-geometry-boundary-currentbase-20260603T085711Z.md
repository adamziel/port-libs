# markerpdf table named-Bbox geometry boundary current base

Slice: `markerpdf-table-geometry-boundary-current-base-20260603T085711Z`

Base: `b658d7df866a87d85e21955ec3e4c2081cbbc693`

## Source truth

- Upstream `tabled-pdf` 0.1.4 schema exposes table result geometry through Surya-style `Bbox` objects for cells, table bboxes, and image bboxes: https://pypi.org/project/tabled-pdf/0.1.4/
- The source package's `tabled/assignment.py` assigns rows/columns by `Bbox(...).intersection_pct(...)` and accesses row/column `bbox`, `width`, `height`, and center-distance helpers.
- The source package's `tabled/extract.py` emits `ExtractPageResult(..., bboxes=[Bbox(bbox=b) ...], image_bboxes=[Bbox(bbox=[0, 0, w, h]) ...])`.

## Implementation

- `TableRecognizer` now normalizes order-independent named Bbox coordinate fields before row/column assignment, table bbox sorting, OCR-line geometry, and supplied crop-boundary review.
- Supported named coordinate shapes:
  - `x1`, `y1`, `x2`, `y2`
  - `x_start`, `y_start`, `x_end`, `y_end`
  - `left`, `top`, `right`, `bottom`
- The existing four-value bbox and four-corner polygon paths remain intact.
- Table crop-boundary review rows now include `coordinate_source` when the source row/column band used named coordinate fields.

## Verification

- `php -l lanes/markerpdf/src/TableRecognizer.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-table-named-bbox-geometry-boundary-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php`
  - `1 test files, 361 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-named-bbox-geometry-boundary-currentbase.php`
  - emitted non-empty WordPress table output
  - reported table crop boundary `240 x 80`
  - reported named row coordinate sources `bbox_xyxy_named_fields`, `bbox_x_start_y_start_fields`, `bbox_left_top_right_bottom_fields`
  - reported named column coordinate sources `bbox_left_top_right_bottom_fields`, `bbox_x_start_y_start_fields`, `bbox_xyxy_named_fields`
  - reported `3` clipped bands and `2` excluded stale bands

## Non-overlap

This slice does not repeat the accepted table crop clipping/exclusion behavior. It keeps that accepted boundary review and adds the upstream handoff normalization layer needed when supplied table geometry arrives as serialized named `Bbox` fields instead of positional `[x1, y1, x2, y2]` arrays.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP `TableRecognizer` and `SuppliedDocumentConverter` supplied-boundary paths. GPU/model OCR, Surya/Texify/Torch execution, visual table recognition, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
