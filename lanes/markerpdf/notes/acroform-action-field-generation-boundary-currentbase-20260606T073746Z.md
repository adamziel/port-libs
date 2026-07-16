# AcroForm Action Field Generation Boundary Current Base

## Source Truth

Upstream markerPDF delegates searchable PDF structure, annotations, and form dictionaries to the PDF parser layer before OCR/model stages. PDF indirect references are generation-qualified, so AcroForm action field lists such as SubmitForm `/Fields`, ResetForm `/Fields`, and Hide `/T` must not resolve a stale `6 0 R` target to the currently selected `6 1` field row.

## Behavior

`PdfAcroFormExtractor` now uses the existing generation-aware review reference walker for AcroForm action field target lists. References whose generation conflicts with the selected object body are dropped, while missing object references are still preserved as unresolved review metadata for WordPress import triage.

This slice covers:

- SubmitForm `/Fields [6 0 R ...]` no longer selecting current field object `6 1`.
- ResetForm `/Fields [6 0 R ...]` no longer selecting current field object `6 1`.
- Hide `/T [6 0 R ...]` no longer selecting current field object `6 1`.
- Missing action targets such as `99 0 R` and `100 0 R` remain in `unresolved_field_objects`.
- Scalar field-name targets remain review metadata.

## Evidence

Red probe before the source edit showed stale generation-zero targets leaking into current-generation action review:

```text
current.email
{"type":"SubmitForm","field_objects":[6,99],"field_names":["current.email","named.extra"],"unresolved":[99]}
current.title
{"type":"Hide","field_objects":[6,10,100],"field_names":["current.email","current.title"],"unresolved":[100]}
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsActionGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS drops stale-generation AcroForm action field references while preserving missing review targets

1 test files, 36 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 49 selected test files (root lock skipped)
49 test files, 3820 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-action-generation-currentbase.php
stale_generation_field_excluded=true
missing_field_targets_preserved_for_review=true
executes_form_actions=false
executes_javascript=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted AcroForm root field generation matching, generation-exact scalar operands, calculation order generation review, parent ownership, direct dictionary materialization, object-stream fields, page widget parent repair, submit/reset resource review, signature lock review, XFA, appearance streams, or form action execution policy. The bounded change is only generation-aware object references inside AcroForm action field target lists.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation map, token-aware reference reader, AcroForm field-name index, action review extractor paths, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF tools, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

## Next

Continue with a non-overlapping native markerPDF parser/review gap around remaining AcroForm field dictionary boundaries, annotations, forms, security preflight, metadata, xref/object-stream repair, stream filters, fonts/CMaps, page geometry, or supplied-boundary table/equation handoffs.
