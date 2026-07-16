# Image XObject CTM Recovery Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T170619Z`
Accepted base: `4b4ed6566d9aa97b39e2a564de2e67000bb01006`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image
rendering handoff: `marker.pdf.extract_text` produces text blocks while image
rendering is handled by `marker.pdf.images.render_image`. In this no-GPU PHP
lane, Image XObject payload bytes remain native review-only media metadata and
must not become WordPress paragraph text.

PDF content-stream `cm` updates the current transformation matrix only with
exactly six numeric operands. A malformed `cm` immediately before an Image
XObject placement is still review-only metadata, but that metadata is scoped to
the placement it actually affects. If a later valid `cm` replaces the CTM before
`Do`, the resulting image placement should be clean and should not inherit the
stale malformed-operator review stack.

## Red Probe

Before the source change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCtmRecoveryBoundaryCurrentBaseTest.php`
  failed with `1 test files / 13 assertions / 1 failures`.
- Failure: the recovered Image XObject still had
  `malformed_ctm_operand_count=1` after a valid replacement CTM had established
  the invocation matrix `[20, 0, 0, 10, 72, 690]`.

## Implementation

- `PdfTextExtractor::contentXObjectInvocationDetails()` now distinguishes valid
  `cm` operands from malformed `cm` operands before dispatching graphics-state
  operator handling.
- Malformed `cm` operands still record `malformed_ctm_operands` exactly as in
  the existing boundary path.
- A valid `cm` clears any pending malformed-CTM review state after the CTM is
  accepted, so subsequent Image XObject `Do` metadata reports the recovered
  placement without a stale malformed-review flag.
- Image payload bytes remain excluded from visible text and review JSON.

## Evidence

- Red-first focused run before implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCtmRecoveryBoundaryCurrentBaseTest.php`
  => `1 test files / 13 assertions / 1 failures`.
- Focused run after implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCtmRecoveryBoundaryCurrentBaseTest.php`
  => `1 test files / 35 assertions / 0 failures`.
- Adjacent Image XObject CTM/Do boundary coverage:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCtmRecoveryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMalformedCtmOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php`
  => `4 test files / 164 assertions / 0 failures`.
- Broader Image XObject current-base family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php`
  => `47 test files / 3217 assertions / 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-ctm-recovery-currentbase.php`
  exits 0 and emits `stale_malformed_cm_review_cleared=true`,
  `recovered_ctm_matrix_applied=true`, `valid_sibling_image_painted=true`,
  and `payload_in_visible_text=false`.
- PHP lint:
  `php -l lanes/markerpdf/src/PdfTextExtractor.php`,
  `php -l lanes/markerpdf/tests/PdfImageXObjectCtmRecoveryBoundaryCurrentBaseTest.php`,
  and
  `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-ctm-recovery-currentbase.php`
  all report no syntax errors.
- Lane status JSON validates with `json_decode(..., JSON_THROW_ON_ERROR)`.
- `git diff --check -- lanes/markerpdf` exits 0.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted Image XObject Do operand tails, malformed
`cm` immediate-placement review, CTM operand arity rejection, text-object Do
boundaries, compatibility-section boundaries, optional-content filtering,
clipping/path review, Form XObject Matrix, Type3 CharProc, image filter
metadata, resource-wrapper, or encrypted fail-closed image review slices. It
only owns the parser-state boundary where a valid replacement `cm` clears stale
malformed-CTM review metadata before Image XObject invocation.

## Dependency Closure

No new support component is needed. This reuses the native PHP content-stream
tokenizer, numeric operand parser, graphics-state stack, Image XObject review
path, stream decoder, and existing WordPress example harness. GPU/OCR/model
execution, PDFium/PIL raster parity, external PDF tools, and network services
remain intentionally out of scope.

## Next Task

Continue non-overlapping native markerPDF parser fidelity around image/filter
metadata, fonts/CMaps, xref repair, metadata, annotations/forms, page geometry,
security preflight, and supplied-boundary table/equation handoffs.
