# markerPDF AcroForm Fields Indirect Reference Operands Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T091802Z`

Base accepted HEAD: `d9949f7212f1baa1739072f7847d9100f9fa82cb`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF parser output before OCR/layout/model stages. In the native no-GPU PHP lane, AcroForm field and widget review must therefore resolve PDF indirect operands before WordPress import metadata is assembled, while malformed wrapper objects remain fail-closed.

This slice maps pure indirect-reference wrapper objects in AcroForm field operands:

- AcroForm `/Fields [40 0 R]` where object `40` is exactly `6 0 R`;
- field `/Kids [50 0 R]` where object `50` is exactly `8 0 R`;
- widget `/Parent 60 0 R` where object `60` is exactly `6 0 R`;
- widget `/P 70 0 R` where object `70` is exactly `3 0 R`;
- page `/Annots [80 0 R]` where object `80` is exactly `8 0 R`.

Reference-wrapper objects with trailing operands such as `98 0 R 6 0 R` stay invalid and do not promote decoy fields/widgets.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectReferenceOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect AcroForm field reference operands before page widget repair (lanes/markerpdf/tests/PdfAcroFormFieldsIndirectReferenceOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'indirect.ref.email',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor::validObjectReferenceFromValue()` now resolves pure indirect-reference wrapper objects recursively with generation and cycle guards.
- `PdfAcroFormExtractor::validObjectReferences()` uses that same resolver for references found inside arrays, so `/Fields`, `/Kids`, page `/Annots`, widget `/Parent`, and widget `/P` share one boundary.
- Tailed reference wrapper objects still fail because `objectReferenceFromValue()` only accepts a complete single reference token with trailing whitespace/comments.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectReferenceOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect AcroForm field reference operands before page widget repair

1 test files, 34 assertions, 0 failures
```

AcroForm field family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroFormFields*CurrentBaseTest.php' | sort)
Focused test run: 50 selected test files (root lock skipped)
50 test files, 2671 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-reference-operands-currentbase.php
```

The smoke exits `0` and emits `fields_reference_wrapper_resolved=true`, `kids_reference_wrapper_resolved=true`, `widget_parent_reference_wrapper_resolved=true`, `widget_page_reference_wrapper_resolved=true`, `page_annots_reference_wrapper_resolved=true`, `tailed_reference_wrappers_excluded=true`, `field_values_review_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object inventory, generation-aware indirect reference parsing, token-aware array scanning, AcroForm field hierarchy repair, page Widget metadata collection, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, form action execution, JavaScript execution, raster rendering, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted AcroForm direct dictionaries, direct widgets, indirect array object boundaries, tailed array/reference-object rejection, page-widget parent ownership, duplicate key handling, generation selection, object-stream fields, XFA/signature/action review, submit/reset/action resources, default-resource appearance metadata, or non-widget subtype boundaries. The bounded behavior is only resolving pure reference-wrapper operands before AcroForm field/page-widget repair while preserving fail-closed tailed-wrapper exclusion.
