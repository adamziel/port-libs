# markerPDF annotations links IsMap boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T073116Z`
Session: `port-dev-markerpdf-annotations-links-20260605T073116Z`
Base accepted HEAD: `7be7cb0f86830fe3775224c988273aed9f59671f`

## Source truth

Upstream markerPDF promotes PDF link references into Markdown after extracting
searchable PDF text, but PDF action execution remains outside import. PDF URI
actions may set `/IsMap true`, meaning activation appends pointer coordinates
to the URI target. The native no-GPU WordPress importer cannot reconstruct
those activation coordinates, so `/IsMap true` must remain review metadata
instead of becoming a static Gutenberg href.

## Behavior

`PdfActionReviewExtractor` now records URI action `/IsMap` booleans as
`uri_is_map` and `requires_activation_coordinates`. Safe static URI actions
keep `review-uri` safety. Safe `/IsMap true` URI actions now use
`coordinate-dependent-uri-review`, which keeps them visible in annotation
action review but outside `PdfLinkAnnotationExtractor` primary span promotion.
Chained `/Next` URI actions with `/IsMap true` receive the same review-only
classification and are not promoted after blocked JavaScript primaries.

## Evidence

Red-first focused command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps coordinate dependent IsMap URI Link annotations review-only before WordPress span promotion
Values are not identical
Expected: false
Actual: NULL

1 test files, 4 assertions, 1 failures
PHP Warning:  Undefined array key "uri_is_map" in .../lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php on line 55
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps coordinate dependent IsMap URI Link annotations review-only before WordPress span promotion

1 test files, 31 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-ismap-boundary-currentbase.php
```

The smoke emits `annotation_objects=[7,8,9]`,
`annotation_action_safety_chains=[["review-uri"],["coordinate-dependent-uri-review"],["blocked-javascript","coordinate-dependent-uri-review"]]`,
`annotation_uri_is_map_flags=[[false],[true],[true]]`,
`promoted_link_objects=[7]`, `coordinate_dependent_uri_promoted=false`,
`ismap_review_only=true`, `annotation_payload_text_excluded_from_visible_text=true`,
and all PDF/Python/model/external-tool execution flags false.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted link URI parsing, catalog URI base resolution,
URI control-byte filtering, `/PA` previous URI review, primary `/Next` chain
gating, remote GoToR review, link CropBox/rotation/UserUnit geometry,
QuadPoints clipping, widget-link inheritance, link presentation metadata,
page `/Annots` token ownership, escaped annotation names, or exact
object-generation link selection. The bounded behavior is only URI action
`/IsMap` activation-coordinate review and non-promotion.

## Dependency closure

No new support component is needed. This reuses the existing native PDF action
review parser, page annotation extractor, link span promoter, and Markdown
post-processor. GPU/model OCR, Surya/Texify/Torch, pypdfium raster execution,
live PDF action execution, and exact upstream model benchmark parity remain
intentionally out of scope for this markerPDF lane.
