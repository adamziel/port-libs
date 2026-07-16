# Remote GoToR Destination View Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260606T174908Z`
Base: `b60cec4d49cb59114ec3c1a229511b181ee5b068`

## Source Truth

This is a native no-GPU markerPDF parser/converter slice. It stays inside searchable-PDF link annotation handling and does not run OCR, Surya, Texify, Torch, Python model workers, external PDF tools, or live service tests.

The local destination validator already treats explicit destination arrays as valid only when the view token is one of the bounded PDF destination view names and the required operands are present. Remote GoToR page-number destination arrays now use that same boundary before they can become WordPress link-span review metadata.

## Behavior

`PdfActionReviewExtractor::remoteDestinationValue()` now rejects malformed remote GoToR page-number destination arrays such as invalid view names or missing required coordinates. Valid remote page-number destinations and named remote destinations remain `remote-document-review`. Rejected remote arrays stay annotation review metadata as `unsupported-action-review` and are not promoted into local WordPress link spans.

This avoids treating malformed remote `/D` arrays as if they were safe remote document link spans while keeping the converter fail-closed and non-executing.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed remote GoToR destination arrays before WordPress link promotion
1 test files, 3 assertions, 1 failures
```

The failing assertion showed invalid `/D [4 /Launch 720]` and missing-coordinate `/D [5 /FitH]` actions being classified as `remote-document-review`.

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS promotes only direct primary Link actions while keeping chained safe actions review-only
PASS keeps remote GoToR Link annotations as review metadata without local page promotion
PASS rejects malformed remote GoToR destination arrays before WordPress link promotion
PASS reviews outline Dest values that resolve to remote GoToR action dictionaries
PASS keeps remote destination action operands out of local TOC and visible WordPress text
4 test files, 155 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-remote-gotor-view-boundary-currentbase.php
```

The smoke reports `promoted_link_objects=[7,10]`, `invalid_view_remote_promoted=false`, `missing_coordinate_remote_promoted=false`, `visible_text_excludes_remote_operands=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted valid remote GoToR action handling, URI/base handling, primary action extraction, named destination review, outline remote destination review, local explicit destination view-mode validation, xref repair, attachment extraction, or OCR/model behavior. The owned boundary is only malformed remote GoToR page-number destination arrays before WordPress link-span promotion.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object parser, action review extractor, link annotation extractor, and Markdown/WordPress span promotion path. GPU/model OCR, visual page analysis, PDFium parity, and external converter execution remain intentionally out of scope for this no-GPU markerPDF lane.

Root harness: not run - isolated micro-slice.
