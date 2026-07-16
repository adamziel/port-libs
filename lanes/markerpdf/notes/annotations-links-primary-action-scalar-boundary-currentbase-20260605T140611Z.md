# markerPDF annotations links primary-action scalar boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T140611Z`

Base accepted HEAD: `4bfdc93c14d76167b74f01a068b7e451ad18fbf7`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, searchable-PDF link and annotation handling is native parser/review behavior before any OCR/model handoff.
- PDF Link annotation `/A` is the primary action dictionary boundary. It must resolve to an action dictionary with an action subtype `/S`; local destinations belong under `/Dest` or under `/S /GoTo /D`, not as scalar or actionless dictionary values directly under `/A`.
- PDF actions, JavaScript, Launch, remote document actions, Python models, PDFium rendering, OCR, and external PDF tools are not executed.

## Implementation

- `PdfActionReviewExtractor::reviewPrimaryAnnotationActionsFromValue()` now requires annotation `/A` to resolve to a dictionary with a name-valued `/S`.
- Scalar `/A (named-target)` and actionless `/A << /D (named-target) >>` no longer donate local destinations to annotation review or WordPress link promotion.
- Valid `/A << /S /URI ... >>` and destination-only `/Dest (named-target)` Link annotations continue through the existing review and span-promotion paths.
- Added `PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-link-primary-action-scalar-boundary-currentbase.php`.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects scalar and actionless-dictionary Link annotation A values before WordPress span promotion
A scalar /A value is not an action dictionary.
Expected: array ()
Actual: local-destination action for named-target
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects scalar and actionless-dictionary Link annotation A values before WordPress span promotion
1 test files, 31 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
Focused test run: 20 selected test files (root lock skipped)
20 test files, 1054 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-primary-action-scalar-boundary-currentbase.php
```

The smoke emits `promoted_link_objects=[7,10]`, `scalar_a_promoted=false`, `actionless_dictionary_promoted=false`, `direct_uri_promoted=true`, `direct_dest_review_page=1`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all PDF action/model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior cases: `1895 -> 1896`.
- WordPress scenarios: `1715 -> 1716`.
- Added 31 focused assertions in the new test file.

## Non-Overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation-name handling, exact object/page generation handling, URI control-byte filtering, catalog URI Base resolution, `/PA` previous URI review, top-level `/A` array rejection, primary direct action selection, remote GoToR review, name-tree `/Limits`, hidden/no-view flags, QuadPoints, rotated/UserUnit geometry, widget field action inheritance, or destination-generation filtering.

The bounded behavior is only scalar and actionless-dictionary values directly under Link annotation `/A` before annotation review and WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, action review resolver, Link annotation extractor, supplied span model, Markdown merge path, and WordPress smoke path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated and intentionally out of scope for this no-GPU parser slice.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around annotations, forms, fonts/CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
