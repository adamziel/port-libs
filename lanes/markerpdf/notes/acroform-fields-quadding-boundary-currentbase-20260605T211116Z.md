# AcroForm Fields Quadding Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T211116Z`

Base accepted HEAD: `51a06c2e4d068494c9869cbc4ab8445059008d96`

## Source Truth

PDF AcroForm variable text fields use `/Q` for quadding: `0` left, `1` center,
and `2` right. MarkerPDF should preserve that as native review metadata before
WordPress import, while keeping field values and appearances out of visible
page text unless explicitly rendered by the PDF content stream.

This slice stays in the current no-GPU markerPDF scope. It does not invoke OCR,
Surya, Texify, Torch, PDFium, JavaScript, appearance streams, external PDF
tools, or model workers.

## Behavior Added

`PdfAcroFormExtractor` now emits `quadding`, `text_alignment`, and
`quadding_review` for AcroForm fields. The review records:

- form-level default `/Q` inherited into terminal text fields;
- parent field `/Q` inherited by child fields;
- terminal field `/Q` values;
- exact-generation indirect numeric references;
- invalid numeric values as resolved but `unknown` and invalid;
- generation-mismatched references as unresolved.

The review metadata is explicitly `review_only`, with appearance rendering,
form action execution, JavaScript execution, and visible text import disabled.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsQuaddingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves AcroForm Q quadding inheritance and generation boundaries before WordPress review
Values are not identical
Expected: 'acroform_field_quadding_boundary'
Actual: NULL

1 test files, 4 assertions, 1 failures
```

## Verification

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsQuaddingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves AcroForm Q quadding inheritance and generation boundaries before WordPress review

1 test files, 94 assertions, 0 failures
```

AcroForm field family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php
Focused test run: 18 selected test files (root lock skipped)
...
18 test files, 1279 assertions, 0 failures
```

Broader AcroForm sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*CurrentBaseTest.php
Focused test run: 40 selected test files (root lock skipped)
...
40 test files, 2679 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-quadding-currentbase.php
```

The smoke emitted center, right, left, unknown, and unresolved quadding review
rows; excluded form values from visible text; and reported no model or external
PDF tool execution.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This avoids the accepted AcroForm field discovery, generation-exact field
reference, alternate/mapping name, max-length, XFA, submit/reset action, and
signature-review clusters. The new behavior is limited to AcroForm `/Q`
quadding metadata and source-boundary review.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF
dictionary parser, field inheritance resolver, exact-generation object lookup,
and form extraction pipeline.

Next task: continue native no-GPU AcroForm review on a non-overlapping boundary,
such as field action dictionary metadata or appearance stream preflight, without
executing actions or rendering appearances.
