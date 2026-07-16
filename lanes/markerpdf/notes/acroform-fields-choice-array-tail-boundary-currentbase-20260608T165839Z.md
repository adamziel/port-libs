# AcroForm Fields Choice Array Tail Boundary Current Base

## Source truth

Native no-GPU markerPDF scope: AcroForm choice metadata is parser/review data before WordPress import. PDF dictionaries store one object per key; a direct array value for `/V`, `/DV`, `/Opt`, or `/I` followed by a non-comment top-level operand is malformed and must not be used as current/default choice values, option labels, or selected indexes. Comment-only tails before the next dictionary key remain valid parser trivia.

## Implementation

- `PdfAcroFormExtractor::mergeFieldAttributes()` now ignores direct array-valued choice attributes `/V`, `/DV`, `/Opt`, and `/I` when the parsed top-level value span has a trailing operand before the next key.
- The check is intentionally bounded to direct choice arrays. Generation-exact indirect choice array objects continue through the existing resolver and existing tests.
- Added focused coverage for a malformed tailed choice field and a comment-only choice field in the same PDF fixture.
- Added a WordPress smoke that emits bounded form-review metadata and confirms no choice payload text becomes visible page text.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects direct AcroForm choice arrays with trailing operands before WordPress review
Values are not identical
Expected: NULL
Actual: array (
  0 => 'publish',
)

1 test files, 6 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects direct AcroForm choice arrays with trailing operands before WordPress review

1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceArrayTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectChoiceArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectArrayTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsArrayObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
5 test files, 749 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-choice-array-tail-currentbase.php
exits 0; emits tailed_choice_arrays_excluded=true, comment_choice_selected_indices=[1], payload_text_exposed=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency closure

No new dependency or support component is needed. This reuses the existing native PDF dictionary/value scanner and AcroForm review path. GPU/OCR/model execution, external PDF tools, and PDF action execution remain intentionally out of scope.

## Non-overlap

This does not repeat accepted AcroForm `/Fields` or `/Kids` direct/indirect array-tail rejection, object-tail rejection, indirect choice array resolution, generation-exact scalar/numeric operands, direct field dictionary materialization, page-owned widget repair, parent ownership boundaries, PDFDocEncoding text strings, XFA/signature/action review, CMaps, image filters, xref repair, OCR/model, or supplied-boundary table/equation handoffs. The bounded behavior is only malformed direct choice array operands used by AcroForm field review metadata.
