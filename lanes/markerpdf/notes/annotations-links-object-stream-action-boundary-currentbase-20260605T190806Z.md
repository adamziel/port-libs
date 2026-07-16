# Link Annotation Object Stream Action Boundary Current Base

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T190806Z`

Accepted base: `df54d12c94aff9eba2ec5d444b33bf5771ac0237`

## Source Truth

- PDF xref-stream type-2 entries select compressed object-stream members as the
  current body for an object number, even when a stale direct same-number object
  body appears elsewhere in the file.
- Link annotation action fields may be indirect dictionaries. Current action
  resolution must use the selected xref/object-stream object map before URI span
  promotion and before unsafe action review.
- Upstream markerPDF's searchable-PDF path keeps annotation/link extraction as
  native PDF parser metadata; it does not execute PDF actions or require OCR,
  Surya, Texify, Torch, browser rendering, or external PDF tools.

## Behavior

- `PdfActionReviewExtractor` now builds its object-value cache from selected
  xref-stream entries when available, including type-2 compressed object stream
  members.
- Link annotations whose current object-stream bodies reference indirect
  action dictionaries now promote the current compressed `/URI` action and
  review the current compressed chained, additional, and previous actions.
- Stale direct same-number action objects remain excluded from link metadata,
  WordPress span promotion, and visible searchable text.

## Evidence

Red-first on accepted base after adding the focused test and before the source
edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 1 failures`; expected
`https://example.com/current-compressed-action`, actual
`https://stale.example.com/direct-action`.

After the source edit:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php`
  => `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectActionSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php`
  => `8 test files, 237 assertions, 0 failures`
- `php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`
  => no syntax errors
- `php -l lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php`
  => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-object-stream-action-currentbase.php`
  => no syntax errors
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-object-stream-action-currentbase.php`
  => emitted `promoted_uri=https://example.com/current-compressed-action`,
  `action_types=["URI","JavaScript"]`,
  `additional_action_uri=mailto:compressed-action@example.test`,
  `previous_action_uri=https://archive.example.com/current-previous-action`,
  `stale_direct_action_excluded=true`,
  `visible_text_excludes_action_payloads=true`
- `git diff --check -- lanes/markerpdf`
  => clean

Root harness: not run - isolated micro-slice.

## Non-overlap

This slice does not repeat the accepted object-stream annotation-body boundary,
indirect action subtype resolution, primary action array/scalar boundaries,
rotated/UserUnit link rectangle promotion, generation-boundary annotation
selection, named destination generation selection, or xref-stream repair
slices. It owns the action-review object map used after a current object-stream
annotation body selects indirect action dictionaries.

## Dependency Closure

No new support component is needed. The patch reuses native PHP stream decoding,
xref-stream parsing, object-stream member extraction, action review, link span
promotion, and the existing WordPress example harness. No GPU/model execution,
OCR, external PDF tools, network services, or live provider tests were used.
