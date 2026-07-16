# AcroForm Indirect Choice Arrays Current-Base Slice

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260607T053239Z`
- Accepted base: `9f18ba88ee76386e943df1faf4ad3dc5a3241d77`
- Behavior: resolve generation-exact indirect array operands for AcroForm choice-field `/V`, `/DV`, `/Opt`, and `/I` review metadata.

## Source Truth

PDF AcroForm choice fields may store selected values, default values, option arrays, and selected option indexes as PDF arrays. Those arrays may be direct operands or indirect objects. The native parser must resolve only the object generation explicitly referenced by the field dictionary. Stale same-object-number arrays from a different generation remain invalid for the current field and must not leak into WordPress-visible text or action execution.

This is native searchable-PDF parser work only. No OCR, Surya, Texify, Torch, model workers, JavaScript, PDF action execution, Python execution, or external PDF tools are involved.

## Patch

- `PdfAcroFormExtractor::pdfValueToPhpValue()` now resolves direct arrays and generation-exact indirect array references through the existing object resolver before scalar fallback.
- `PdfAcroFormExtractor::optionsFromEffective()` now accepts generation-exact indirect `/Opt` arrays.
- `PdfAcroFormExtractor::integerArrayFromEffective()` now accepts generation-exact indirect `/I` arrays.
- Existing generation validation and cycle protection remain centralized in `arrayBodyFromValueOrReference()`.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectChoiceArrayBoundaryCurrentBaseTest.php
1 test files, 9 assertions, 1 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectChoiceArrayBoundaryCurrentBaseTest.php
1 test files, 42 assertions, 0 failures
```

AcroForm fields family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
33 test files, 1903 assertions, 0 failures
```

Full AcroForm family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
56 test files, 4112 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-choice-arrays-currentbase.php
```

The smoke exits `0` with generation-exact indirect current values, default values, option labels, and selected indexes resolved; stale generation arrays excluded; form values excluded from visible text; and all action/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted direct `/Fields` or `/Kids` array handling, indirect field-root handling, duplicate field/kid boundaries, scalar generation operands, numeric field attributes, field type names, object-stream form resolution, page-widget parent repair, XFA/signature/action review, or trailer/xref generation repair. The bounded behavior is only AcroForm choice arrays used by `/V`, `/DV`, `/Opt`, and `/I`.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded native PHP object resolver and AcroForm parser; upstream GPU/model parity remains intentionally out of scope under the no-GPU markerPDF override.
