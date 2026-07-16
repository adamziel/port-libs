# markerPDF Link Annotation Page Geometry Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260603T090446Z`

Base accepted HEAD: `a934fd3337210e4ce0a15739eef0bd11ba3529ba`

## Source Truth

- Upstream markerPDF delegates searchable-PDF text geometry to pdftext dictionaries; supplied span bboxes are already in display-space page coordinates.
- The accepted native text-markup slice already maps annotation `/QuadPoints` through inherited page boxes, `/Rotate`, and page-local `/UserUnit` before intersecting supplied pdftext spans.
- PDF link annotation `/Rect` values are also page user-space rectangles, so the native link span promotion needs the same bounded page-geometry conversion before WordPress import.

## Implementation

- `PdfLinkAnnotationExtractor` now derives page geometry from inherited `/MediaBox`, `/CropBox`, `/Rotate`, and page-local `/UserUnit`.
- Extracted link rows preserve raw PDF page-space `rect` and add `pdftext_rect`, `page_bbox`, `page_rotation`, `page_user_unit`, and `display_page_bbox` review metadata.
- `applyLinksToPages()` uses the transformed `marker_pdftext_display` rect only when the supplied page advertises pdftext-style page `bbox` plus matching `rotation`; legacy supplied pages continue to use raw PDF page-space rectangles.

## WordPress Smoke

Added `examples/wordpress-pdf-link-rotation-userunit-currentbase.php`. It builds an inherited `/CropBox` + `/Rotate 90` page with page-local indirect `/UserUnit 2`, then verifies the URI link attaches only to the transformed pdftext-display span and not to the raw page-space decoy span.

Smoke output records:

- `support_component=native-pdf-link-annotation-page-geometry`
- `link_rect_coordinate_space=marker_pdftext_display`
- raw `page_rect=[30,150,110,170]`
- transformed `pdftext_rect=[220,20,260,180]`
- `raw_decoy_linked=false`
- no PDF actions, Python/models, or external PDF tools executed

## Verification

- Red-first before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php` failed the new rotated link case because `pdftext_rect` was missing; existing seven link tests passed.
- After implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php` passed with `1 test files, 125 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-link-rotation-userunit-currentbase.php` emitted non-empty WordPress paragraph/link output and smoke metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object/page-tree parser, annotation traversal, action review, destination handling, and accepted page geometry conversion pattern. Full upstream markerPDF Python/model/pdftext/pypdfium benchmark parity remains intentionally outside the current no-GPU/model scope.

## Non-Overlap

This does not repeat URI safety filtering, widget link promotion, named-destination review metadata, annotation action review, text-markup QuadPoints rotation, or general annotation geometry review. The new behavior is specifically `/Link` and link-like `/Widget` `/Rect` conversion through page boxes, rotation, and UserUnit before supplied pdftext span intersection.
