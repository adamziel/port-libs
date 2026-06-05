# markerPDF AcroForm Fields Parent Reference Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T104716Z`

## Source Truth

Upstream markerPDF depends on the PDF parser layer to expose searchable PDF content and form metadata before WordPress/Markdown conversion. For native no-GPU form review, an AcroForm field tree child listed in `/Kids` is accepted only when an explicit child `/Parent` points back to the listing field. Mismatched parent references are malformed field-tree edges and must not import spoofed field names, values, or widget geometry.

## Implementation

- `PdfAcroFormExtractor` now checks explicit child `/Parent` references while descending field-tree `/Kids`.
- The same ownership boundary is applied to field extraction, field-name mapping, field-tree reachability, and page-widget promotion.
- Child fields with mismatched `/Parent` references are excluded from WordPress form review metadata.
- Child widgets with mismatched `/Parent` references are not attached to the valid field and are not promoted through page annotations unless their own parent tree owns them.

## Red-First Evidence

Before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 7 assertions, 2 failures`; `profile.spoof` was imported from a mismatched child field, and widget object `12` was attached to `article.title` despite pointing at another parent.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php` passed with `1 test files, 45 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php` passed with `9 test files, 813 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(AcroForm|SecurityAcroForm).*Test\.php$' | sort)` passed with `34 test files, 3198 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-parent-reference-currentbase.php` emitted `mismatched_child_field_excluded=true`, `mismatched_child_widget_excluded=true`, `field_values_review_only=true`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget discovery, direct Widget `/Fields` normalization, child-field branch normalization, token-aware arrays, indirect `/Fields`/`/Kids` arrays, generation-exact field refs, trailer-root selection, comment-split references, unowned widget-parent rejection, cycle suppression, scalar generation, widget appearance/action/XFA/signature review, submit/reset actions, security preflight, link/action boundaries, or metadata/image/xref/parser clusters.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, dictionary parser, generation-checked indirect references, page-widget map, AcroForm field hierarchy/value-state review, and WordPress smoke path. Full OCR/model execution, XFA layout binding, form action execution, JavaScript, rendering, signing, pypdfium/PIL, Python models, and external PDF tools remain intentionally out of scope.
