# markerpdf annotations links markup generation current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260606T083354Z`

Base accepted HEAD: `caecf0ca19e48aa4938a4375b8a2c64f59e87a84`

## Scope

This patch stays inside the native no-GPU markerPDF scope. It covers
searchable-PDF text-markup annotation references where page `/Annots` points at
a non-zero object generation. Link annotations already preserve
`annotation_generation`; this slice carries the same exact generation metadata
through Highlight/Underline/Squiggly/StrikeOut review rows and WordPress
review-span metadata.

## Source Truth

Upstream markerPDF keeps searchable-PDF annotation/link handling in the parser
and document-conversion boundary before OCR/model stages. At the native PHP
boundary, PDF indirect references are object-number plus generation pairs.
Generation-exact annotation rows are review metadata only; stale generation
decoys must not be promoted into WordPress links, markup review annotations, or
visible paragraph text.

## Implementation

- `PdfMarkupAnnotationExtractor` now passes annotation object generations from
  page `/Annots` references into `markupFromAnnotationBody()`.
- Extracted markup rows include `annotation_generation` for referenced
  generation-bearing annotations.
- Applied WordPress review annotations now carry the same
  `annotation_generation` metadata beside `annotation_object`.
- The focused fixture proves current generation-1 Link and Highlight
  annotations are promoted while generation-0 stale Link/Highlight decoys stay
  out of review rows, Markdown links, and visible text.

## Evidence

Red-first focused check after adding the test and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkMarkupGenerationMetadataCurrentBaseTest.php`

Result: failed as expected because `array_column($markups[0]['markups'], 'annotation_generation')`
was empty; `1 test files / 8 assertions / 1 failures`.

Focused check after the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkMarkupGenerationMetadataCurrentBaseTest.php`

Result: `1 test files / 25 assertions / 0 failures`.

Focused adjacent check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkMarkupGenerationMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php`

Result: `3 test files / 152 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-annotation-markup-generation-currentbase.php`

Result: emitted `annotation_generations=[1,1]`, `link_generations=[1]`,
`markup_generations=[1]`, `span_markup_generation=1`,
`stale_generation_excluded=true`,
`annotation_review_text_excluded_from_visible_text=true`, and all PDF action,
JavaScript, Python/model, and external-tool execution flags `false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object map,
generation-aware annotation reference resolution, markup geometry/review
extractor, link extractor, text extractor, and WordPress smoke harness. OCR,
Surya/Texify/Torch, PDFium/PIL rendering, action execution, external PDF
tools, and exact upstream model benchmark parity remain intentionally out of
scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted page `/Annots` duplicate-key selection, escaped
`/Ann#6fts` lookup, exact annotation object selection, exact page `/P`
generation membership, Link annotation generation output, destination
generation boundaries, annotation action chains, widget field inheritance,
markup QuadPoints geometry, xref repair, forms, security, outline metadata,
image/filter, font/CMap, OCR, or supplied table/equation behavior. The bounded
behavior is only preserving the already-selected text-markup annotation object
generation into review metadata and WordPress spans.

## Next Task

Continue native no-GPU markerPDF work around remaining searchable-PDF
annotation/form/security/page-geometry boundaries, stream filters, fonts/CMaps,
xref repair, image/filter metadata, and supplied-boundary table/equation
handoffs.
