# AcroForm Scalar Object Tail Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T053423Z`

Accepted base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Source Truth

This slice stays inside the native no-GPU markerPDF scope. PDF AcroForm field metadata entries such as `/T`, `/TU`, `/TM`, `/V`, `/DV`, and `/Opt` may be indirect objects, but a scalar reference must resolve to one complete PDF value. A referenced object body with extra top-level operands after that scalar is ambiguous and must not be treated as trustworthy WordPress form-review metadata.

The implementation reuses the existing native PDF value reader and whitespace/comment boundary checks. It does not add OCR, model execution, raster inspection, JavaScript execution, form-action execution, external PDF tools, or a new support component.

## Behavior

`PdfAcroFormExtractor` now resolves indirect scalar strings, names, and numbers only when the referenced object body contains exactly one complete PDF value followed by PDF whitespace/comments. Current-generation scalar objects such as `(Tailed value) 77`, `(bad.name) 50 0 R`, or `/Bad /Extra` are rejected before they populate field names, labels, mapping names, current/default values, numeric metadata, or choice-option labels/exports.

Direct dictionary scalar values and complete referenced scalar objects continue to work. The new WordPress smoke keeps valid form fields visible in review metadata while proving tailed scalar object text does not surface in review JSON or extracted text.

## Evidence

Red-first focused run before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsScalarObjectTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects current-generation AcroForm scalar objects with trailing operands before field review
Expected: ['safe.scalar','safe.choice','safe.unnamed.export']
Actual: ['safe.scalar','safe.choice','tailed.partial.name.must.not.surface']
1 test files, 1 assertions, 1 failures
```

Focused run after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsScalarObjectTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects current-generation AcroForm scalar objects with trailing operands before field review
1 test files, 44 assertions, 0 failures
```

Adjacent AcroForm boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsXrefGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsArrayObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsReferenceObjectTailBoundaryCurrentBaseTest.php
4 test files, 679 assertions, 0 failures
```

Full AcroForm family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroForm*Test.php' | sort)
71 test files, 4800 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-scalar-object-tail-currentbase.php
```

The smoke exits `0` and reports `tailed_value_objects_rejected=true`, `tailed_label_objects_rejected=true`, `tailed_option_objects_rejected=true`, `tailed_partial_name_rejected=true`, `field_values_visible_in_text=false`, `tailed_scalar_text_visible=false`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new dependency or support-library component is needed. The patch reuses the existing native PHP object dictionary, PDF value tokenizer, and whitespace/comment boundary helpers.

## Next

Continue with non-overlapping native searchable-PDF behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
