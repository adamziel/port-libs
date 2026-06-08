# AcroForm Fields Inherited Actions Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T061651Z`

Accepted base: `1e4cd8062b7ccd6c5b3583fb768f31d2954dea93`

## Source Truth

PDF AcroForm field trees inherit field attributes from non-terminal parent fields. Field additional-actions dictionaries (`/AA`) can define validation, calculation, format, and other review-relevant actions; the native markerPDF scope records these as metadata and never executes PDF actions or JavaScript.

## Change

`PdfAcroFormExtractor` now treats field `/AA` as an effective field attribute:

- terminal child fields inherit parent `/AA` action dictionaries for review metadata;
- terminal `/AA` dictionaries override inherited parent `/AA` dictionaries;
- inherited action rows preserve the parent source object;
- SubmitForm and ResetForm rows explicitly report `executes_javascript=false`;
- visible PDF text extraction still excludes field values, action targets, and scripts.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsInheritedActionsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL inherits parent AcroForm additional actions as review metadata on terminal child fields
Expected: ['FT', 'AA']
Actual: ['FT']
FAIL lets terminal AcroForm additional actions override inherited parent actions
Expected: ['V', 'AA']
Actual: ['V']
1 test files, 8 assertions, 2 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsInheritedActionsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS inherits parent AcroForm additional actions as review metadata on terminal child fields
PASS lets terminal AcroForm additional actions override inherited parent actions
1 test files, 54 assertions, 0 failures
```

Adjacent AcroForm boundary/action set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsInheritedActionsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsActionGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
5 test files, 692 assertions, 0 failures
```

Full AcroForm focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php' | sort)
71 test files, 4810 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-inherited-actions-currentbase.php
```

The smoke exits 0 and emits `title_inherited_action_triggers=["V","C"]`, `slug_terminal_action_triggers=["F"]`, `terminal_aa_overrides_parent_aa=true`, `review_only_text_excluded_from_visible_text=true`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat AcroForm field dictionary materialization, page-widget repair, direct widget parent repair, action dictionary exclusion, action generation filtering, calculation-order review, XFA/signature widgets, runtime converter preflight, OCR/model behavior, or external PDF tooling. The change is limited to inherited field `/AA` metadata and direct SubmitForm/ResetForm non-execution flags.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF object scanner, token-aware dictionary parser, AcroForm field hierarchy extraction, action-chain reviewer, and WordPress example smoke harness. No Python, OCR, CUDA, Surya/Texify/Torch models, raster rendering, PDF action execution, external PDF tools, live services, or network access were used.
