# markerPDF AcroForm Null Kids Parent Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260609T001711Z`

Base accepted HEAD: `5319793e63d462f6bfc4ded2804124d217af8a52`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text and review metadata before OCR/layout/model stages. Under the current no-GPU markerPDF scope, AcroForm field-tree handling is native searchable-PDF parser behavior for WordPress import review.

PDF dictionary entries whose value is the null object are treated as absent at parser boundaries. For split AcroForm fields, a page Widget annotation can carry `/Parent` pointing at a terminal field dictionary. If that parent field declares `/Kids null`, native review should behave like the omitted-`/Kids` page-widget repair path and promote the parent field. Explicit empty, mismatched, or malformed non-array `/Kids` values remain authoritative and still block inferred ownership.

## Red-First Evidence

Before the source edit, the focused test failed because `/Kids null` was treated as a present Kids boundary, so the page Widget's parent field was dropped:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNullKidsParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats explicit null Kids as absent during page-widget parent boundary repair (lanes/markerpdf/tests/PdfAcroFormFieldsNullKidsParentBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'nullkids.email',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor::fieldHasKids()` now resolves direct or indirect PDF null values as absent while keeping any non-null `/Kids` value as an explicit ownership boundary.
- Added a focused test with one repaired page Widget parent field that declares `/Kids null`, plus explicit-empty, mismatched-Kids, and malformed non-array Kids decoys that must stay excluded from review metadata and visible text.
- Added a WordPress smoke that emits machine-readable review metadata without executing form actions, JavaScript, Python models, OCR, raster rendering, or external PDF tools.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNullKidsParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats explicit null Kids as absent during page-widget parent boundary repair

1 test files, 46 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS normalizes direct Widget Fields entries to Parent fields that omit Kids
PASS bounds page widget AcroForm parent repair by escaped Parent ownership before WordPress field review
PASS bounds AcroForm Parent inheritance to parent Kids ownership before WordPress field review
PASS rejects AcroForm child field dictionaries whose explicit Parent points outside the listed Kids branch
PASS rejects AcroForm child widget dictionaries whose explicit Parent points outside the field Kids branch

4 test files, 191 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfAcroFormFields*CurrentBaseTest.php' | sort)
Focused test run: 72 selected test files (root lock skipped)
...
72 test files, 3674 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-null-kids-parent-currentbase.php
```

The smoke emits `null_kids_parent_selected=true`, `field_value_selected=true`, `widget_promoted_from_page_annots=true`, `explicit_empty_kids_excluded=true`, `mismatched_kids_excluded=true`, `malformed_non_array_kids_excluded=true`, `visible_page_text_selected=true`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary/array parser, page-tree walker, page Widget annotation map, AcroForm field hierarchy walker, and WordPress smoke path. Live OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, form action execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted omitted-`/Kids` page-widget repair, direct Widget `/Fields` parent repair, explicit empty, mismatched, or malformed Kids rejection, direct parent dictionary repair, parent ownership rejection, child branch/root normalization, field overlap dedupe, cycle guards, generation-exact references, object-stream field recovery, token/comment array parsing, wrong-page `/P` rejection, XFA/signature/action review, submit/reset review, default-resource appearance metadata, or pdftext dictionary layout/order metadata. The bounded behavior is only a page Widget `/Parent` field whose field dictionary declares `/Kids null`.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around forms, annotations, fonts, CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
