# AcroForm Fields Action Dictionary Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260607T093655Z`

Accepted base: `b86d159cdf99a07a68249d9af6c697b1a15bfa78`

## Source Truth

PDF AcroForm `/Fields` and field `/Kids` arrays name field dictionaries or widget annotations. PDF action dictionaries use `/S` action subtypes such as `/Hide`, `/URI`, `/JavaScript`, `/SubmitForm`, and `/ResetForm`; their `/T` or `/Fields` operands are action targets and must not promote those dictionaries into form fields.

## Change

`PdfAcroFormExtractor::isNonAcroFormFieldDictionary()` now rejects known PDF action dictionaries by `/S` action type before generic field-candidate checks promote objects with `/T`, `/TM`, `/FT`, `/Kids`, or widget shape. Widget dictionaries remain eligible, and real field `/AA` action dictionaries continue to be extracted as review-only action metadata.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsActionDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL excludes AcroForm action dictionaries that advertise target names as fields
Expected: [article.title, article.summary]
Actual: [article.title.decoy.kid.uri, article.summary, decoy.root.hide, decoy.root.javascript]
1 test files, 1 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsActionDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes AcroForm action dictionaries that advertise target names as fields
1 test files, 33 assertions, 0 failures
```

Adjacent AcroForm field family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
Focused test run: 37 selected test files (root lock skipped)
37 test files, 2058 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-action-dictionary-boundary-currentbase.php
```

The smoke exits 0 and emits `decoy_action_fields_excluded=true`, `real_hide_action_reviewed=true`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not overlap the accepted AcroForm generation-boundary slice, direct dictionary materialization, page-widget repair, action generation target filtering, page resource inheritance, OCR/model handoffs, or external PDF tooling. The change is limited to excluding action dictionaries from native AcroForm field candidate discovery.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF object scanner, name-token parser, AcroForm field extractor, and review-only action metadata extraction. No Python, OCR, CUDA, model execution, raster rendering, PDF action execution, external PDF tools, or live services were used.
