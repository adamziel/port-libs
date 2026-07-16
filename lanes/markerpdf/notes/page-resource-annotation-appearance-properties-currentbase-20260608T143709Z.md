# markerpdf page resource annotation appearance Properties current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T143709Z`

Base accepted HEAD: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Behavior

Pinned upstream markerPDF source remains `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable PDF text reaches parser/PDFium/pdftext layers before OCR or model handoffs. Under the native no-GPU PHP boundary, selected annotation `/AP /N` streams are Form XObjects and their `/Resources` dictionaries scope fonts, optional-content properties, and marked-content `/Properties`.

This patch aliases annotation appearance marked-content resource names before appending the appearance stream to the page stream. A resource-less appearance can still inherit page `/Properties`, but an appearance with explicit local `/Resources /Properties` now owns same-named `/ActualText` or `/Alt` replacements for its own stream.

Before this patch, a FreeText appearance with `/Resources << /Properties << /SharedActual ... >> >>` was appended with raw `/SharedActual`; the page-level marked-content map then replaced it with inherited page ActualText.

## Evidence

Red-first focused test after adding the fixture and before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearancePropertiesCurrentBaseTest.php
```

Result: `1 test files, 1 assertions, 1 failures`; the second appearance resolved to `Inherited page ActualText` instead of `Appearance local ActualText`.

Focused test after the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearancePropertiesCurrentBaseTest.php
```

Result: `1 test files, 14 assertions, 0 failures`.

Adjacent resource sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearancePropertiesCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearanceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceOptionalContentWrapperCurrentBaseTest.php
```

Result: `4 test files, 316 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-annotation-appearance-properties-currentbase.php
```

Result: exit 0, with `inherited_page_actual_text_imported=true`, `appearance_local_actual_text_imported=true`, `shared_property_name_scoped=true`, `raw_glyph_noise_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP object table, page-tree resource resolver, Form XObject resource-owner resolver, stream decoder, content tokenizer, marked-content replacement path, annotation appearance extraction, and WordPress smoke renderer. OCR/model execution, PDFium rendering, live upstream model parity, decryption/password validation, JavaScript/action execution, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat accepted page-tree resource inheritance, annotation appearance omitted-resource inheritance, explicit empty appearance resource blocking, Form-local `/Properties` scoping, optional-content Properties filtering, font/CMap resource inheritance, image XObject resource review, xref repair, metadata extraction, forms/security preflight, supplied table/equation handoffs, or OCR/model behavior. The bounded behavior is only marked-content `/Properties` scoping for selected annotation appearance Form XObject resources before WordPress text import.
