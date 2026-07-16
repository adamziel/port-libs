# markerPDF annotations links previous URI boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T054113Z`
Session: `port-dev-markerpdf-annotations-links-20260605T054113Z`
Base accepted HEAD: `4b80fbb617415ca3af053741139f8ed1fe4bccdf`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the markerPDF manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; its searchable-PDF import path delegates native page/link behavior to PDF parser/PDFium-style readers and does not execute PDF annotation actions during import.
- PDF Link annotation `/PA` stores a previous URI action. PDF Association summarizes ISO 32000-2 Table 176 as defining `/PA` for the original URI action, and PDFBox exposes this as `PDAnnotationLink` previous-URI accessors.
- This native no-GPU slice maps that boundary for WordPress import: `/PA` is review-only metadata, while `/A` or `/Dest` remains the only primary source for clickable link promotion.

Sources:

- https://pdfa.org/pdf-a-and-external-references/
- https://pdfbox.apache.org/docs/2.0.0/javadocs/org/apache/pdfbox/pdmodel/interactive/annotation/PDAnnotationLink.html

## Implementation

- `PdfActionReviewExtractor::reviewAnnotationActions()` now parses annotation `/PA` values into `previous_uri_actions` using the existing bounded action reviewer, including safe URI review, chained `/Next` review, generation-exact indirect action resolution, and no action execution.
- `PdfAnnotationExtractor` carries non-empty `previous_uri_actions` on page annotation review rows and applies the same destination/StructParent context enrichment used for other action review rows.
- `PdfLinkAnnotationExtractor` carries `previous_uri_actions` onto promoted link rows and supplied pdftext spans as `link_previous_uri_actions`, without allowing those previous actions to satisfy primary link promotion.
- `PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php` covers three current page links: a local `/A /GoTo` with `/PA` original URI, a current `/A /URI` with indirect `/PA`, and a `/PA`-only annotation that stays unpromoted.
- `wordpress-pdf-link-annotation-previous-uri-currentbase.php` emits WordPress Markdown with only the current URI link while preserving previous URI actions as review-only metadata.

## Red first

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "previous_uri_actions" in .../lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php on line 62
FAIL keeps Link annotation previous URI actions review-only before WordPress span promotion
array_column(): Argument #1 ($array) must be of type array, null given

1 test files, 4 assertions, 1 failures
```

## Verification

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps Link annotation previous URI actions review-only before WordPress span promotion

1 test files, 39 assertions, 0 failures
```

Adjacent annotation/link family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnots*LinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
Focused test run: 20 selected test files (root lock skipped)
20 test files, 1087 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-previous-uri-currentbase.php
```

The smoke emits `annotation_objects=[7,8,9]`, `promoted_link_objects=[7,8]`, previous URI review values for `original-guide`, `original-followup`, `old-current-docs`, and `previous-only`, `previous_uri_actions_promoted=false`, `current_uri_promoted=true`, `local_destination_stays_non_href=true`, `previous_metadata_on_link_span=true`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all PDF/Python/model/external-tool execution flags false.

Lint and whitespace:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-previous-uri-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation-name handling, exact object-generation link selection, URI control-byte filtering, catalog URI base resolution, primary `/Next` chain gating, remote GoToR direct-primary review, link CropBox/rotation/UserUnit geometry, QuadPoints clipping, widget-link inheritance, link presentation metadata, or DCTDecode image/filter boundaries. The new behavior is specifically `/PA` previous URI action review metadata without primary WordPress href promotion.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, action review extractor, page annotation extractor, link annotation extractor, supplied pdftext span merger, and Markdown postprocessor. GPU/model OCR, Surya/Texify/Torch, pypdfium raster execution, live PDF action execution, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF lane.
