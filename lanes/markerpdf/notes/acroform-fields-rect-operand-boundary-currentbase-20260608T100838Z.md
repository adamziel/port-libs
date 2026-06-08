# AcroForm Fields Rect Operand Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T100838Z`

Accepted base: `aa643f82345e8cc58e03e48973d0de73611eadc6`

## Source Truth

PDF widget annotations use `/Rect` rectangle arrays with four numeric operands. The existing native generic annotation extractor already treats annotation `/Rect` as an exact four-number boundary and fails closed when extra operands trail the value. This slice brings AcroForm widget review to the same boundary so stale geometry or object references after a widget rectangle cannot become WordPress form metadata.

## Change

`PdfAcroFormExtractor::rectFromAnnotation()` now:

- reads the last top-level `/Rect` span and rejects trailing operands before the next dictionary key;
- resolves direct or indirect numeric rectangle operands exactly;
- rejects direct or indirect arrays with extra, missing, or non-numeric operands instead of accepting the first four numbers;
- preserves page-owned fields/widgets and valid indirect numeric rectangle arrays.

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRectOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed AcroForm widget Rect operands while preserving exact indirect rectangles
1 test files, 23 assertions, 0 failures
```

Adjacent AcroForm fields family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
Focused test run: 51 selected test files (root lock skipped)
51 test files, 2694 assertions, 0 failures
```

Broader AcroForm family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*CurrentBaseTest\.php$' | sort)
Focused test run: 73 selected test files (root lock skipped)
73 test files, 4094 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-rect-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `valid_indirect_rect_preserved=true`, `extra_rect_operand_rejected=true`, `trailing_rect_operand_rejected=true`, `indirect_extra_rect_operand_rejected=true`, `stale_trailing_rect_decoy_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Non-Overlap

This does not repeat accepted AcroForm token-aware field keys, page-widget repair, direct dictionary materialization, generation boundaries, parent ownership, action dictionary rejection, appearance/action review, XFA/signature review, annotation link geometry, page-box geometry, OCR/model handoffs, or external PDF tooling. The bounded behavior is only AcroForm widget `/Rect` operand validation before field review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, token-aware dictionary/array parser, indirect numeric object resolver, page-widget map, AcroForm field traversal, and WordPress smoke path. No Python, OCR, CUDA, model execution, raster rendering, PDF action execution, external PDF tools, live services, or credentialed providers were used.
