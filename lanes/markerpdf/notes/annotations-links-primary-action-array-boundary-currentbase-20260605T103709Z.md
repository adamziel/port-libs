# markerpdf annotations links primary-action array boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T103709Z`

Base accepted HEAD: `d9d41d3151c8a8cec51322c58b72834b0637dde0`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In this no-GPU lane, searchable-PDF page links and action metadata are native parser responsibilities before model/OCR handoff.
- PDF Link annotations use `/A` for one primary action dictionary and `/Dest` for a destination. Action arrays are valid under action `/Next`, but a top-level annotation `/A [...]` is malformed and must not donate a primary WordPress link target.
- PDF actions, JavaScript, Launch, remote document actions, Python models, PDFium, OCR, and external PDF tools are not executed.

## Implementation

- `PdfActionReviewExtractor` now routes annotation `/A` through a primary-action boundary helper.
- If `/A` resolves directly or indirectly to a PDF array, primary action review returns no actions, so `PdfLinkAnnotationExtractor` cannot promote spoof URI or remote/launch targets.
- Valid action dictionaries still pass through the existing action walker, including `/Next` arrays as non-executing review metadata.
- Added `PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-link-primary-action-array-boundary-currentbase.php`.

## Red First

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects primary Link annotation A action arrays before WordPress span promotion
A direct primary action array is malformed and must not be treated as a primary Link action.
Expected: array ()
Actual: review-uri and blocked-javascript actions from the /A array
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects primary Link annotation A action arrays before WordPress span promotion
1 test files, 30 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 645 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSubmitResetAppearanceLockCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 277 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-primary-action-array-boundary-currentbase.php
```

The smoke emits `promoted_link_objects=[7]`, `primary_action_safeties=[["review-uri","review-uri","blocked-javascript"],[],[]]`, `valid_next_array_preserved=true`, `direct_array_promoted=false`, `indirect_array_promoted=false`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all model/external-tool/action execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/Annots` top-level ownership, token-boundary parsing, escaped annotation keys, annotation `/P` page-reference ownership, exact object generation handling, StructTree generation context, URI control-byte filtering, primary direct action selection, previous URI `/PA` review, remote GoToR review, name-tree limits, catalog URI Base, hidden/no-view flags, QuadPoints, rotated/UserUnit geometry, or widget field action inheritance.

The new behavior is specifically the top-level annotation `/A` array boundary before Link promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF action parser, object/value resolver, annotation/link extractors, supplied span model, and Markdown merge path. Full live OCR, Surya/Texify/Torch model execution, pypdfium rendering, media playback, JavaScript/PDF action execution, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
