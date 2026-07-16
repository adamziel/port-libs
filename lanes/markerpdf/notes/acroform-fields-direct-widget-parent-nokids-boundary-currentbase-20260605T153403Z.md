# markerPDF AcroForm Direct Widget Parent Without Kids Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T153403Z`

Base accepted HEAD: `9f5c2e5a2a488d9988b860638e73fa38efd5184e`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text and review metadata before OCR/layout/model stages. Under the current no-GPU markerPDF scope, AcroForm field-tree handling is native searchable-PDF parser behavior for WordPress import review.

PDF AcroForm fields may be split between a field dictionary and a Widget annotation. A malformed `/AcroForm /Fields` array can list the pure Widget directly instead of the parent field. When that Widget has an explicit `/Parent` pointing at a field dictionary that omits `/Kids`, native review should normalize to the parent field, matching the accepted page-widget parent-without-`/Kids` boundary. Explicit empty or mismatched `/Kids` remains authoritative and must still exclude decoys.

## Red-First Evidence

Before the source edit, the focused test failed because the direct Widget `/Fields` entry was treated as already reachable and blocked parent repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes direct Widget Fields entries to Parent fields that omit Kids
Expected: array (
  0 => 'direct.nokids',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor::rootFieldReferencesFromAcroFormReferences()` now normalizes a pure Widget listed directly in `/Fields` to its `/Parent` field when that parent is a valid field candidate and either omits `/Kids` or owns the Widget through `/Kids`.
- Explicit parent `/Kids []` and mismatched `/Kids [other-widget]` branches remain rejected.
- Added a focused test fixture with one accepted direct Widget parent-without-`/Kids` field and two direct Widget decoys.
- Added a WordPress smoke that emits a review table and machine-readable metadata without executing form actions, JavaScript, Python models, or external PDF tools.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes direct Widget Fields entries to Parent fields that omit Kids
1 test files, 38 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 732 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 36 selected test files (root lock skipped)
36 test files, 3200 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-widget-parent-nokids-currentbase.php
```

The smoke emits `direct_widget_parent_without_kids_normalized=true`, `explicit_empty_kids_parent_excluded=true`, `explicit_mismatched_kids_parent_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary/array parser, AcroForm field-tree walker, page Widget annotation map, field hierarchy/value review logic, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, form action execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted page-owned Widget repair, parent-without-`/Kids` page annotation repair, direct Widget `/Fields` entries with owning `/Kids`, parent ownership rejection, child branch/root normalization, field overlap dedupe, cycle guards, generation-exact references, object-stream field recovery, token/comment array parsing, wrong-page `/P` rejection, XFA/signature/action review, submit/reset review, default-resource appearance metadata, or pdftext dictionary layout/order metadata. The bounded behavior is only pure Widget entries listed directly in `/AcroForm /Fields` where the Widget's valid parent field omits `/Kids`.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around forms, annotations, fonts, CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
