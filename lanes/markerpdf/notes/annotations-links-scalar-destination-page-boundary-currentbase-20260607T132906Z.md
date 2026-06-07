# markerPDF annotations links scalar-destination page boundary current-base

Session: `port-dev-markerpdf-annotations-links-20260607T132906Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260607T132906Z`
Accepted base: `d2d46ce443975597c743342bd6d4681bc9535c2a`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and link/navigation metadata through pdftext/PDFium parser boundaries before Marker merges links into downstream Markdown. Under the current no-GPU markerPDF scope, this PHP lane owns native PDF annotation and action review boundaries without running OCR, Surya, Texify, Torch, PDFium, JavaScript, PDF actions, or external PDF tools.

PDF local destinations are only useful to WordPress span promotion when they resolve to a page in the current page tree. Existing named-destination handling already rejected out-of-range page indexes, but the direct Link annotation action-review path still accepted any non-negative scalar destination page index.

## Behavior

`PdfActionReviewExtractor::destinationViewDetails()` now bounds direct scalar local destinations to the current page count. A valid scalar `/Dest 1` in a two-page PDF remains a reviewable `local-destination`, while `/Dest 9` no longer donates a destination action. A malformed `/A << /S /GoTo /D 12 >>` action remains review-only as `unsupported-action-review`, but it is not eligible for `PdfLinkAnnotationExtractor` primary link promotion.

The WordPress smoke proves only annotation objects `7` and `10` are promoted: object `7` is the in-range scalar local destination and object `10` is a safe URI. Out-of-range scalar direct and GoTo action destinations stay out of spans and visible Gutenberg paragraph text.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationScalarDestinationPageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds scalar Link annotation destination page indexes before WordPress span promotion
A direct scalar /Dest outside the current page count is malformed.
Expected: array (
)
Actual: array (
  0 =>
  array (
    'action_type' => 'GoTo',
    'safety' => 'local-destination',
    'page' => 9,
    'destination_page' => 9,
    'destination_page_label' => '10',
    ...
  ),
)
1 test files, 5 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php

php -l lanes/markerpdf/tests/PdfLinkAnnotationScalarDestinationPageBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationScalarDestinationPageBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-link-scalar-destination-page-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-scalar-destination-page-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationScalarDestinationPageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds scalar Link annotation destination page indexes before WordPress span promotion
1 test files, 29 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfLinkAnnotation|PdfAnnotationLink|PdfNamedDestination|PdfAnnotationExtractorTest).*\.php$' | sort)
Focused test run: 96 selected test files (root lock skipped)
96 test files, 3322 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-link-scalar-destination-page-boundary-currentbase.php
exits 0; emits promoted_link_objects=[7,10], valid_scalar_destination_page=1, out_of_range_direct_promoted=false, out_of_range_action_promoted=false, annotation_payload_text_visible=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted scalar/actionless `/A` rejection, same-page positionless `/Fit` review-only span behavior, named-destination page operand bounds, name-tree limits, destination generation checks, URI controls, primary action arrays, duplicate action keys, optional-content visibility, object-stream annotation bodies, QuadPoints geometry, widget inheritance, annotation struct-tree context, or PageLabels work. The bounded behavior is only direct scalar local-destination page indexes in Link annotation `/Dest` and `/A << /S /GoTo /D ... >>` paths before WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, annotation extractor, action-review extractor, link annotation span promoter, Markdown postprocessor, and WordPress smoke path. GPU/model/OCR execution, Surya/Torch/Texify, pypdfium/PDFium runtime execution, Streamlit/FastAPI workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
