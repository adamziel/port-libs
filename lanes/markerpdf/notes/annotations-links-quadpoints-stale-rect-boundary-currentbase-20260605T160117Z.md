# markerPDF Link QuadPoints stale Rect boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T160117Z`
Session: `port-dev-markerpdf-annotations-links-20260605T160117Z`
Base accepted HEAD: `240321533bfab61882c8ca2197727e323087613b`

## Source truth

Upstream `sddai/markerPDF` remains pinned in the manifest at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU scope,
searchable-PDF link handling is native parser/span-promotion behavior before
OCR/model handoff.

PDF Link annotations use `/QuadPoints` to describe clickable quadrilateral text
regions. The native importer should use valid visible quads for supplied
pdftext/Marker span matching even when a producer leaves the required `/Rect`
stale or off-page, while the stale Rect itself must stay review metadata and
must not donate links to adjacent text.

PDF actions, JavaScript, live OCR, Python models, PDFium rendering, and external
PDF tools are not executed.

## Implementation

- `PdfLinkAnnotationExtractor` no longer rejects a Link solely because `/Rect`
  has no visible page-box area when `/QuadPoints` contains at least one visible
  quad.
- Rect-only off-page annotations are still excluded.
- Link rows keep raw `/Rect`, clipping metadata, visible quad rectangles, and
  review-only annotation contents.
- `applyLinksToPages()` continues matching spans through quad candidates, so
  the stale Rect cannot link a rect-only decoy span.

## Red first

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsStaleRectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses visible Link QuadPoints when the annotation Rect is stale or off-page
Values are not identical
Expected: 1
Actual: 0
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsStaleRectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses visible Link QuadPoints when the annotation Rect is stale or off-page
1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsStaleRectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 169 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfLinkAnnotation*Test.php' -o -name 'PdfAnnotationLink*Test.php' -o -name 'PdfPageAnnots*Test.php' -o -name 'PdfPageAnnotationWidgetLinkCurrentBaseTest.php' -o -name 'PdfPageWidgetFieldActionLinkCurrentBaseTest.php' \) | sort)
Focused test run: 34 selected test files (root lock skipped)
34 test files, 1180 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-quadpoints-stale-rect-currentbase.php
```

The smoke emits `promoted_annotation_objects=[7]`,
`quad_rescue_linked=true`, `rect_decoy_linked=false`,
`visible_text_imported=true`, `visible_text_excludes_link_review_payloads=true`,
`executes_pdf_actions=false`, `executes_javascript=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status delta

- Focused PHP behavior cases: `2056 -> 2057`.
- WordPress scenarios: `1776 -> 1777`.
- Added 1 focused test file with 18 assertions and 1 WordPress smoke.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation
names, exact annotation object generation, exact page `/P` ownership, hidden
or no-view flags, catalog `/URI /Base`, URI control bytes, `/IsMap`, name-tree
`/Limits`, destination generation, remote GoToR, `/PA` previous URI review,
primary `/A` shape gating, widget field inheritance, link presentation
metadata, CropBox clipping for Rect-only links, rotation/UserUnit mapping,
valid Link `/QuadPoints` parsing, malformed QuadPoints grouping, overlap
specificity, text-markup `/QuadPoints`, or StructTree link context.

The bounded behavior is only admission of a Link whose visible QuadPoints are
valid when its raw `/Rect` is stale/off-page, without using that stale Rect as a
clickable span candidate.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
annotation action reviewer, page geometry transforms, supplied marker/pdftext
span model, Markdown post-processor, and WordPress smoke path. Live OCR,
Surya/Torch/Texify models, pypdfium/PDFium rendering, PDF action execution, and
external PDF tools remain intentionally out of scope for the no-GPU markerPDF
lane.

## Next task

Continue with non-overlapping native searchable-PDF parser behavior around
annotations/forms, fonts/CMaps, stream filters, xref repair, metadata, outlines,
page geometry, image/filter metadata, and supplied-boundary table/equation
handoffs.
