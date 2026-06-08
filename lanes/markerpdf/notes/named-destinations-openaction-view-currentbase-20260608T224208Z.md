# Named Destinations OpenAction View Boundary Current Base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T224208Z`

## Source Truth

- Upstream markerPDF keeps searchable-PDF navigation in parser/review metadata before OCR or model work. The native PHP path should therefore expose catalog `/OpenAction` GoTo destinations as non-executing review metadata, while keeping destination operands out of imported WordPress body text.
- Existing native coverage already preserved page-view operands for catalog page-view metadata and for annotation/outline named-destination actions. This slice closes the review-row boundary where a catalog `/OpenAction` local GoTo resolved to the right page/name but dropped the named destination `/Fit*` or `/XYZ` view operands.
- This remains in the no-GPU markerPDF scope: no live OCR, Surya/Texify/Torch, PDFium/PIL rendering, Python, model workers, external PDF tools, or action execution.

## Change

- `PdfOutlineExtractor::localOpenDestinationReview()` now reuses the existing `destinationViewDetails()` resolver for local GoTo OpenAction rows.
- Local OpenAction review rows now carry `view_mode`, `view_position`, and `view_parameters`, including raw-byte PDF string name-tree collisions where two names decode to the same visible text.
- URI, Launch, remote GoToR, and other action review rows keep their existing shape and safety classifications.
- Added a focused decoded-collision OpenAction test and a WordPress smoke proving `/XYZ` view metadata remains review-only and does not leak into visible text.

## Red-First Evidence

Before the source change, the new focused file failed because the review row omitted view metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationOpenActionViewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves decoded-collision named destination view metadata on catalog OpenAction review rows
Expected: 'XYZ'
Actual: NULL
FAIL keeps catalog OpenAction named destination operands out of visible WordPress text

1 test files, 19 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationOpenActionViewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves decoded-collision named destination view metadata on catalog OpenAction review rows
PASS keeps catalog OpenAction named destination operands out of visible WordPress text

1 test files, 32 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
23 PASS cases
1 test files, 306 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationOpenActionViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
11 PASS cases
5 test files, 181 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationOpenActionViewBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/tests/PdfOutlineExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-openaction-view-currentbase.php
```

All report no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-openaction-view-currentbase.php
```

The smoke exits 0 and reports `decoded_collision_count=2`, `open_action_page=2`, `open_action_view_mode=XYZ`, `open_action_review_view_matches_catalog=true`, `visible_text_excludes_destination_labels=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted standalone named-destination extraction, duplicate key ordering, aliases/cycles, name-tree byte limits, link/annotation destination action promotion, outline TOC destination view metadata, catalog page-view metadata, remote GoTo/Launch/URI action safety review, PageLabels, xref repair, table geometry, OCR, or model behavior. The bounded behavior is only catalog local GoTo OpenAction review rows preserving native named-destination page-view operands.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object parser, name-tree destination map, raw PDF string lookup keys, catalog OpenAction review path, destination view resolver, text extractor, and WordPress smoke path. Live OCR, model inference, PDF rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
