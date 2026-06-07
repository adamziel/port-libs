# markerPDF AcroForm Fields Direct Child Parent Widget Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260607T085633Z`

Base accepted HEAD: `63be7b48de2468cb308ecab366fd7ed1adc53468`

## Source Truth

Native no-GPU markerPDF/PDF parser behavior for searchable PDFs and WordPress review metadata. AcroForm field trees may include direct field dictionaries in an indirect parent field's `/Kids` array. Because direct dictionaries have no stable object number, some PDFs point the child field's widget `/Parent` back at the containing indirect parent. The import path must keep that page-owned widget attached to the synthetic direct child field while still rejecting mismatched direct child branches.

No Python, CUDA, OCR, model execution, PDF action execution, signing, signature validation, appearance rendering, or external PDF tools are used.

## Red-First Probe

A throwaway fixture with parent field `10 0 R`, direct child dictionary `/Parent 10 0 R /T (email) /Kids [12 0 R]`, and page widget `12 0 R` with `/Parent 10 0 R` extracted the field but returned an empty widget list before the fix:

```text
[
    [
        13,
        "profile.email",
        "direct-child@example.test",
        []
    ]
]
```

The WordPress-visible page text stayed clean, but the page annotation review row was lost.

## Implementation

- `PdfAcroFormExtractor` now records synthetic direct field dictionaries materialized from a parent field's `/Kids` array.
- `fieldTreeChildOwnedByParent()` still requires exact `/Parent` ownership for normal child objects.
- For widgets under a synthetic direct child only, it also accepts the widget when its `/Parent` points at the containing indirect field that owned the direct child dictionary.
- Mismatched direct child branches remain excluded.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectChildParentWidgetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS retains page widgets whose direct child field Parent points at the owning indirect parent

1 test files, 52 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
Focused test run: 36 selected test files (root lock skipped)
36 test files, 2025 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 59 selected test files (root lock skipped)
59 test files, 4234 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-child-parent-widget-currentbase.php
```

The smoke exits `0` and reports `direct_child_parent_preserved=true`, `ancestor_parent_widget_retained=true`, `mismatched_direct_child_excluded=true`, `field_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsDirectChildParentWidgetBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-child-parent-widget-currentbase.php
```

All reported no syntax errors.

## Non-Overlap

This does not repeat accepted AcroForm field tree cycles, direct widget Fields normalization, direct dictionaries without explicit `/Parent`, indirect array direct dictionaries without ancestor widget parents, mismatched indirect child/widget Parent rejection, page widget parent repair, duplicate Parent/P key handling, object-stream fields, xref-selected generations, XFA/signature/action review, CMap/filter owner boundaries, or generic annotation/link behavior. The bounded behavior is only widgets in a direct child field `/Kids` branch whose widget `/Parent` points at the owning indirect parent field.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, AcroForm direct-dictionary materialization, field-tree ownership checks, page annotation widget mapping, text extraction, and WordPress review smoke output. Full appearance rendering, JavaScript/action execution, form submission/reset execution, OCR/model extraction, signing, and signature validation remain out of scope under the current no-GPU/no-action lane boundary.
