# AcroForm Direct Array Tail Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T161958Z`

Accepted base: `bc9489e331853d7b5b38ea37ea420a29310b5ae4`

## Source Truth

This slice stays inside the native no-GPU markerPDF scope. PDF dictionary values
for AcroForm `/Fields` and field `/Kids` entries may be direct arrays or
references to array objects, but the named top-level value must end cleanly
before the next dictionary key. A stray scalar or object reference after a
direct array is an ambiguous tail and must not be treated as a valid sibling
field or child branch.

The implementation reuses the existing native PDF tokenizer, array reader, and
top-level named-value tail boundary checks. It does not add OCR, model
execution, raster inspection, JavaScript execution, form-action execution,
external PDF tools, or a new support component.

## Behavior

`PdfAcroFormExtractor` now rejects direct `/Fields [..]` and `/Kids [..]`
values when a trailing top-level operand appears before the next dictionary key.
The same boundary is applied before materializing direct dictionaries inside a
named array, so malformed array tails cannot be converted into synthetic field
objects.

Valid page-owned widget repair remains available. If a tailed `/Fields` array is
discarded, page `/Annots` can still recover a valid parent field through the
widget `/Parent` relationship. If a tailed `/Kids` array is discarded, terminal
field metadata is preserved while malformed child traversal is skipped and
page-owned widgets are attached only through valid page annotations.

## Evidence

Red-first focused run before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects direct AcroForm Fields arrays with trailing operands before page-widget repair
Expected: ['valid.page.repair']
Actual: ['tailed.direct.fields.decoy','valid.page.repair']
FAIL rejects direct AcroForm Kids arrays with trailing operands before terminal field review
Expected: ['valid.parent']
Actual: ['valid.parent.malformed.direct.kids.decoy']
1 test files, 2 assertions, 2 failures
```

Focused run after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects direct AcroForm Fields arrays with trailing operands before page-widget repair
PASS rejects direct AcroForm Kids arrays with trailing operands before terminal field review
1 test files, 60 assertions, 0 failures
```

AcroForm family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroForm*Test.php' | sort)
85 test files, 5425 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-array-tail-currentbase.php
```

The smoke exits `0` and reports `direct_fields_array_tail_rejected=true`,
`direct_kids_array_tail_rejected=true`, `page_widget_repair_preserved=true`,
`form_values_visible_in_text=false`, `executes_form_actions=false`,
`executes_javascript=false`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted indirect array-object tail, indirect reference
object tail, direct catalog AcroForm root tail, object dictionary owner,
action-dictionary boundary, scalar-object tail, token-aware reference-array, or
page-widget parent repair slices. The new boundary is specifically for direct
top-level array values inside AcroForm field dictionaries when extra operands
appear between the array and the next dictionary key.

## Dependency Closure

No new dependency or support-library component is needed. The patch reuses the
existing native PHP object dictionary scanner, PDF array reader, indirect object
resolver, and named-value tail boundary helper.

## Next

Continue with non-overlapping native searchable-PDF behavior around fonts,
CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page
geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
