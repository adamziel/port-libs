# markerPDF annotations links duplicate action subtype boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260607T051530Z`

Base accepted HEAD: `12b7d6fa80b46d2718da92097c53b244165d9445`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, searchable-PDF link annotation action review is native parser behavior before any model/OCR handoff.
- PDF action dictionaries use `/S` as the action subtype. A selected action dictionary with duplicate decoded `/S` keys is ambiguous and must not donate a WordPress href, even if the selected last subtype is `/URI`.
- Chained `/Next` actions remain non-executing review metadata. PDF actions, JavaScript, Launch, remote document actions, Python models, PDFium rendering, OCR, and external PDF tools are not executed.

## Implementation

- `PdfActionReviewExtractor::reviewActionsFromValue()` now detects duplicate decoded `/S` keys on the selected resolved action dictionary.
- Duplicate-subtype action dictionaries produce a `malformed-action-dictionary` review row with duplicate-key metadata instead of a promotable `review-uri`, `local-destination`, or `remote-document-review` row.
- `/Next` chain review still runs after the malformed primary row, but `PdfLinkAnnotationExtractor` cannot promote those chained rows to supplied WordPress spans.
- Added `PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-link-duplicate-action-subtype-currentbase.php`.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps duplicate Link action subtype dictionaries review-only before WordPress span promotion
Values are not identical
Expected: array (
  0 => 'malformed-action-dictionary',
  1 => 'review-uri',
)
Actual: array (
  0 => 'review-uri',
  1 => 'review-uri',
)
1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps duplicate Link action subtype dictionaries review-only before WordPress span promotion
1 test files, 30 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 561 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-duplicate-action-subtype-currentbase.php
```

The smoke exits 0 and emits `promoted_link_objects=[7,9]`, `malformed_action_safety=malformed-action-dictionary`, `malformed_action_duplicate_keys=[S]`, `duplicate_subtype_promoted=false`, `valid_sibling_links_promoted=true`, `chained_followup_preserved_in_annotation_review=true`, `chained_followup_excluded_from_promoted_links=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation names, generation-exact annotation selection, optional-content-hidden link exclusion, indirect `/S` subtype resolution, duplicate annotation `/A` or `/Dest` action-key review, duplicate selected action `/URI` or `/Next` review, primary `/A` array/scalar rejection, direct primary versus `/Next` promotion, previous URI `/PA` review, URI Base resolution, IsMap, remote GoToR, name-tree Limits, object-stream action selection, QuadPoints/rotation/UserUnit geometry, or widget field action inheritance. The bounded behavior is only duplicate decoded `/S` keys inside a selected action dictionary before Link annotation promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF tokenizer/object resolver, action-review parser, page annotation/link extractors, supplied span model, Markdown merge path, and WordPress smoke harness. Full live OCR, Surya/Texify/Torch model execution, pypdfium rendering, JavaScript/PDF action execution, media playback, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
