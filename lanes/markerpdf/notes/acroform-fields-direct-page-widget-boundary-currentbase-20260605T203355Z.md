# markerPDF AcroForm direct page Widget field boundary current-base

Session: `port-dev-markerpdf-acroform-fields-20260605T203355Z`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T203355Z`
Base accepted HEAD: `bd7df865700dfaabbc10e2b866ce008e83e43e09`

## Behavior

PDF form fields normally start at catalog `/AcroForm /Fields`, but malformed or producer-generated PDFs can also expose page-owned Widget annotations directly inside a page `/Annots` array. The existing repair path already handled indirect page Widget annotations; this slice adds the direct page annotation boundary by materializing top-level direct Widget dictionaries into synthetic in-memory objects before `pageWidgetMap()`.

The new boundary preserves:

- direct inline Widget field dictionaries as review-only AcroForm fields;
- direct page Widget dictionaries whose `/Parent` field omits `/Kids`;
- matching second-page `/P` references;
- exclusion for wrong-page `/P` widgets, non-Widget annotation dictionaries, and parent fields with explicit empty `/Kids`;
- form values, alternate labels, mapping names, actions, and annotation payloads as review metadata only, never visible WordPress paragraph text.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL materializes direct page Widget annotations before AcroForm field repair
Values are not identical
Expected: array (
  0 => 'direct.inline',
  1 => 'parent.direct',
  2 => 'second.direct',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS materializes direct page Widget annotations before AcroForm field repair

1 test files, 55 assertions, 0 failures
```

Focused AcroForm regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
Focused test run: 44 selected test files (root lock skipped)
44 test files, 3652 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-page-widget-currentbase.php
```

The smoke emitted `field_names=["direct.inline","parent.direct","second.direct"]`, `excluded_decoy_fields=["wrongpage.direct","emptykids.direct","text.annotation.decoy"]`, `form_values_used_as_visible_text=false`, and `executes_python_or_models=false`.

## Non-Overlap

This does not repeat accepted indirect page Widget annotation repair, direct Widget entries in `/AcroForm /Fields`, direct field dictionaries in `/Fields` or `/Kids`, child-root normalization, generation-exact references, indirect `/Fields` and `/Kids` arrays, indirect widget `/Rect` and `/F`, duplicate `/Fields` or `/Kids`, comment-only Widget subtype markers, unowned parent rejection, explicit empty `/Kids` rejection for indirect widgets, widget appearance/action/XFA/signature review, annotation link promotion, xref repair, stream filters, images, CMaps, OCR, or supplied table/equation handoffs.

The bounded behavior is only direct top-level page `/Annots` Widget dictionaries before AcroForm field repair.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, PDF value parser, page tree walker, page annotation widget map, field hierarchy builder, AcroForm value/action review code, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium rendering, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
