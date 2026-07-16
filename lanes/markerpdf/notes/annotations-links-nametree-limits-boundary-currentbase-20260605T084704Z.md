# markerPDF link annotation name-tree Limits boundary

Session: `port-dev-markerpdf-annotations-links-20260605T084704Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T084704Z`
Base accepted HEAD: `2ae1928a75c28b9c5973a7f1a99a0f16b37e9c23`

## Source Truth

Upstream markerPDF delegates searchable PDF page text/navigation extraction to pdftext/PDFium at the parser boundary, while this lane keeps annotation actions non-executing and review-only. PDF catalog `/Names /Dests` name trees use `/Limits` to bound child and leaf names; the existing native named-destination extractor already enforced that boundary for standalone destination rows.

This slice applies the same native name-tree limit pruning to Link annotation named-destination resolution before WordPress span promotion. The behavior is parser-level and does not require OCR, Surya, Texify, Torch, PDFium/PIL raster execution, JavaScript execution, or external PDF tools.

## Implementation

`PdfActionReviewExtractor::collectNameTreeDestinations()` now carries inherited and local `/Limits` while traversing catalog `/Names /Dests`. It intersects valid parent/child ranges, falls back to inherited limits for malformed leaves that match none of their local keys, tracks indirect kids by object and generation, and skips out-of-range name/value pairs before action review rows feed `PdfLinkAnnotationExtractor`.

The focused fixture has three link annotations:

- object `7`: `/Dest (Current Link)` inside the root and child limits, promoted as a local destination link;
- object `8`: `/Dest (Stale Link)` in a stale child outside the inherited limits, retained only as page annotation metadata and not promoted;
- object `9`: direct safe `/URI`, still promoted as a normal review URI link.

## Evidence

Syntax:

`php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`
`php -l lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php`
`php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-nametree-limits-currentbase.php`

Result: no syntax errors detected.

Focused behavior:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`.

Adjacent link/name-tree gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`

Result: `8 test files, 352 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-link-annotation-nametree-limits-currentbase.php`

Result: emitted `promoted_link_objects=[7,9]`, `stale_destination_promoted=false`, `direct_uri_promoted=true`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Whitespace/patch hygiene:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat direct URI link promotion, link rectangle geometry/UserUnit rotation, escaped annotation dictionaries, generation-exact destination references, primary/previous annotation action chains, `/IsMap`, action flag review, page annotation StructTree context, standalone named-destination `/Limits`, or indirect named-destination view/page operands. It is limited to the action-review name-tree walker used by Link annotation destination promotion.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, generation-aware indirect resolver, action review extractor, named-destination normalization, link span promotion, supplied pdftext page arrays, and WordPress smoke renderer. Full upstream runtime/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with a non-overlapping native PDF parser/review gap, preferably AcroForm action/value dictionaries, remaining annotation action boundaries, page geometry/resource handoff, xref/object-stream repair, stream filter metadata, or attachment/security preflight behavior with focused PHP evidence.
