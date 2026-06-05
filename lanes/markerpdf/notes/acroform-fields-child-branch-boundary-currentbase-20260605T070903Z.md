# markerPDF AcroForm Fields Child Branch Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T070903Z`

Accepted base: `96835b31f0b7d31c68967e2c8b5127f6a9eff04e`

## Source Truth

- Upstream markerPDF delegates searchable-PDF parsing to PDF parser layers before OCR/model stages. Under the current no-GPU markerPDF scope, this slice maps the native PDF object boundary for AcroForm field-tree recovery.
- PDF AcroForm `/Fields` arrays should list root fields, but malformed current-base files can point directly at child field dictionaries. The native PHP importer must preserve inherited parent field context for the referenced child while not walking unlisted sibling fields under the same parent.

## Change

- `PdfAcroFormExtractor` now builds an ancestor context for field references that point at child dictionaries. Parent `/FT`, `/DV`, `/DA`, `/MaxLen`, `/TU`, and `/TM` metadata remain available to the child, but extraction starts from the referenced child object rather than replacing it with the full parent root.
- Pure Widget entries in `/Fields` still normalize to the parent root, preserving the accepted direct-widget behavior.
- The new focused fixture proves sibling field `profile.secret` is not imported from the parent `/Kids` array when `/Fields` references only child `profile.email`.

## Red-First Evidence

Before the extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds malformed child AcroForm Fields entries to the referenced branch
Values are not identical
Expected: array (
  0 => 'profile.email',
)
Actual: array (
  0 => 'profile.email',
  1 => 'profile.secret',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds malformed child AcroForm Fields entries to the referenced branch

1 test files, 34 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
15 PASS lines

1 test files, 457 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php
Focused test run: 31 selected test files (root lock skipped)
72 PASS lines

31 test files, 3066 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-child-branch-boundary-currentbase.php
```

The smoke exits `0` and emits `field_names=["profile.email"]`, `sibling_field_imported=false`, `field_value_visible_text_exposed=false`, `parent_value_visible_text_exposed=false`, `sibling_value_visible_text_exposed=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, pure Widget `/Fields` root normalization, token-aware escaped `/Fields` parsing, indirect `/Fields` and `/Kids` arrays, generation-exact field references, indirect scalar/numeric/type operands, alternate `/TU` and `/TM` review, comment-only widget subtype exclusion, child-root normalization when the entire parent branch is intended, widget appearance/action/XFA/signature review, submit/reset review, page widget link promotion, security preflight, xref repair, stream filters, image handling, CMaps, outlines, annotations, or metadata clusters. The bounded behavior is specifically branch-bounded traversal when a malformed `/Fields` array directly references a child field that has unlisted sibling children.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object scanner, generation-valid reference checks, dictionary/array parser, field hierarchy builder, page widget map, action-safe AcroForm review metadata, and existing WordPress smoke path. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
