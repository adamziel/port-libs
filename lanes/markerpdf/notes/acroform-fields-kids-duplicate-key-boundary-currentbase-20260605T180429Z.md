# markerPDF AcroForm Fields Kids Duplicate-Key Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T180429Z`

Base accepted HEAD: `2c19a701a31b0f790d90d0420fa2b95cd56a6265`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF field and text extraction to PDF parser layers before OCR/layout/model stages.
- PDF AcroForm fields are dictionaries whose `/Kids` arrays define child fields and widget annotations. The current native parser already treats repeated top-level `/Fields` keys as a last-key boundary; field-level `/Kids` must follow the same current-dictionary boundary so stale earlier branches do not merge into WordPress form review.
- The no-GPU markerPDF scope keeps this as native parser/review metadata only: form values, stale child labels, and widget payloads stay out of visible WordPress paragraphs, and no form action, model, Python, or external PDF tool executes.

## Implementation

- `PdfAcroFormExtractor::kidReferences()` now resolves `/Kids` with `lastTopLevelValueAfterName()`.
- This aligns field traversal, parent ownership checks, cycle handling, and page-widget repair with the accepted duplicate `/Fields` behavior.
- Added `PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php` proving a stale first `/Kids` branch (`profile.secret`) and a detached first-branch widget are excluded while the later current branch imports `profile.email` and `profile.status` as review metadata.
- Added `wordpress-pdf-acroform-fields-kids-duplicate-key-currentbase.php` as the WordPress smoke path.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last top-level AcroForm Kids key before WordPress field review
Values are not identical
Expected: array (
  0 => 'profile.email',
  1 => 'profile.status',
)
Actual: array (
  0 => 'profile.secret',
)
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-kids-duplicate-key-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-kids-duplicate-key-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last top-level AcroForm Kids key before WordPress field review
1 test files, 44 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsKidsDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsOverlapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 882 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-kids-duplicate-key-currentbase.php
emits last_kids_branch_selected=true, stale_first_kids_branch_excluded=true, first_kids_widget_decoy_excluded=true, field_values_review_only=true, visible_text="Visible duplicate AcroForm Kids body", executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm duplicate `/Fields` handling, escaped `/Fie#6Cds` parsing, token-aware reference arrays, direct field dictionaries, direct Widget `/Fields` normalization, parent ownership checks, page-widget repair, child-branch bounding, overlapping field deduplication, generation-exact field references, object-stream field expansion, widget appearance/action/XFA/signature review, submit/reset action review, annotation link promotion, xref repair, stream filters, metadata, or attachment behavior. The bounded behavior is specifically duplicate top-level `/Kids` keys inside AcroForm field dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, dictionary parser, last top-level dictionary value scanner, AcroForm field-tree traversal, page-widget map, field hierarchy review, and WordPress smoke output. Full appearance rendering, JavaScript/form action execution, XFA layout/data binding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around remaining AcroForm/widget review edges, annotations/forms, page geometry/resource handoff, xref/object-stream repair, stream-filter metadata, security preflight, or attachment review behavior.
