# AcroForm Fields Branch Repair Current-Base Slice

Session: `port-dev-markerpdf-acroform-fields-20260605T101255Z`
Base: `4ad60712d10804701fb3a159914426a65c11dc92`

## Source Truth

This slice maps native PDF AcroForm field-tree behavior under the current no-GPU markerPDF scope. PDF form fields are hierarchical: a widget annotation can point at a terminal field through `/Parent`, and that field can inherit names/defaults from ancestors only when the ancestor field's `/Kids` tree owns the child branch. Page `/Annots` can reveal page-owned widgets that are omitted from malformed `/AcroForm /Fields`, but repairing that omission must not import unrelated sibling fields from an unlisted parent root.

## Behavior

`PdfAcroFormExtractor` now promotes page-owned Widget annotations and direct Widget `/Fields` entries through the immediate parent field branch, then relies on the existing verified `/Parent` plus `/Kids` walk for inherited names, default values, field types, and max-length metadata.

The red probe before the fix produced:

```text
12 profile.email value=editor@example.test widgets=[14]
12 profile.email value=editor@example.test widgets=[14]
16 profile.status value=publish widgets=[18]
```

and, when the parent had a detached private sibling, imported that parent-root sibling too. After the fix:

- the already listed `profile.email` child appears once;
- the page-owned sibling `profile.status` is reviewed through its own terminal branch;
- detached `profile.secret` stays excluded;
- direct Widget `/Fields` entries whose `/Parent /Kids` does not own the widget are rejected.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php`
  - `1 test files, 39 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`
  - `9 test files, 1577 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php`
  - `33 test files, 3059 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-branch-repair-currentbase.php`
  - emits `listed_child_branch_preserved_once=true`, `page_owned_sibling_branch_promoted=true`, `detached_sibling_branch_excluded=true`, `parent_root_not_imported_wholesale=true`, `values_visible_in_text=false`, and external/model execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm field token parsing, comments, indirect arrays, indirect numeric/string/type operands, generation-exact references, direct widget normalization, child-branch selection, parent ownership rejection, cycles, trailer-root selection, default resources, action review, XFA, signature, widget appearance, or page widget link promotion. The new boundary is specifically page-widget/direct-widget repair choosing the immediate owned field branch instead of importing the unlisted parent root wholesale.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF object scanning, token-aware dictionary/array parsing, generation-exact indirect references, AcroForm field-tree traversal, page annotation widget mapping, action review metadata, visible text extraction, and the WordPress smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium rendering, browser/API workers, signature validation, and PDF action execution remain intentionally out of scope under the current markerPDF no-GPU directive.
