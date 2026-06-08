# markerPDF AcroForm Fields Indirect Widget Subtype Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T114351Z`
Base accepted HEAD: `92b623591d4c99d822de39f3b30bbbffe20bf3a9`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF parser output before OCR/model stages. In native no-GPU scope, AcroForm field review must treat page-owned `/Subtype /Widget` annotations as form widgets, but PDF dictionary values can be indirect objects. Generation mismatches must still fail closed.

## Behavior

- `PdfAcroFormExtractor` now resolves `/Subtype` and `/Type` name operands through the existing generation-checked object-reference helper when classifying AcroForm field and widget dictionaries.
- Valid widgets such as `<< /Type /Annot /Subtype 30 1 R ... >>` attach to listed field `/Kids` and page-widget repair when object `30 1` is `/Widget`.
- Non-widget indirect subtype targets such as `/Text`, and stale-generation references such as `/Subtype 32 0 R` when only `32 1` is `/Widget`, remain excluded from AcroForm review and visible WordPress text.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectWidgetSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves generation-exact indirect Widget subtype names before AcroForm field review (lanes/markerpdf/tests/PdfAcroFormFieldsIndirectWidgetSubtypeBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'listed.indirect.widget',
  1 => 'page.indirect.widget',
)
Actual: array (
  0 => 'listed.indirect.widget',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectWidgetSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves generation-exact indirect Widget subtype names before AcroForm field review

1 test files, 48 assertions, 0 failures
```

Adjacent field-boundary coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectWidgetSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsNonWidgetSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsNonFieldParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectReferenceOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
6 test files, 748 assertions, 0 failures
```

Full AcroForm-focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort) lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
79 test files, 5249 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-widget-subtype-currentbase.php
```

The smoke exits 0 and emits `listed_indirect_widget_subtype_resolved=true`, `page_widget_repair_indirect_subtype_resolved=true`, `text_annotation_decoy_excluded=true`, `stale_generation_widget_subtype_rejected=true`, `field_values_visible_in_text=false`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, generation-aware indirect-reference validation, dictionary/name token parser, AcroForm field-tree walker, and page Widget metadata collector. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, JavaScript/form action execution, decryption, signature validation, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted AcroForm action dictionary exclusion, non-widget direct `/Subtype` rejection, typed non-field parent rejection, direct widget materialization, indirect `/Fields` or `/Kids` array resolution, object-stream field repair, generation-exact field references, indirect `/FT` field type resolution, page-tree indirect `/Kids`, direct parent dictionary repair, duplicate-key handling, XFA/signature/action review, annotations outside Widget repair, xref repair, or OCR/model handoffs. The bounded behavior is only generation-exact indirect `/Subtype /Widget` name operands before AcroForm field and page-widget review.
