# markerPDF AcroForm Alternate Mapping Name Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T012540Z`
Base accepted HEAD: `c0afeab573c7ee1ef1cf900a1f4e33962e9c0b34`

## Source Truth

Upstream markerPDF routes searchable-PDF content through parser/pdftext layers before conversion, and the current markerPDF lane stays in the native no-GPU parser/review scope. This slice maps the PDF AcroForm field dictionary boundary for `/TU` alternate field names and `/TM` mapping names. WordPress import can use those names for review/export labels, but they are metadata and must not become extracted page text.

No OCR, Surya, Texify, Torch/model worker, pypdfium/PDFium rendering, JavaScript, PDF action execution, browser, live service, or external PDF tool was executed.

## Behavior

- `PdfAcroFormExtractor` now preserves terminal field/widget `/TU` as `alternate_name`.
- Field hierarchy path rows now carry `alternate_name` alongside `partial_name`, full field name, and mapping name.
- Each field now has `field_name_review` metadata with the selected WordPress review label, explicit mapping-name state, path name metadata, and non-executing/review-only flags.
- `/TM` remains the field mapping name; `/TU` is preferred as the WordPress review label when present.
- Field values, alternate names, mapping names, detached decoys, form actions, and JavaScript stay out of extracted visible page text.

## Red-First Evidence

Before implementation, the added focused case failed on missing `/TU` metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves AcroForm alternate and mapping names as review metadata only
Expected: 'Public email label'
Actual: NULL
1 test files, 181 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 213 assertions, 0 failures
```

Adjacent AcroForm/security family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '(PdfAcroForm|PdfSecurityAcroForm).*Test\.php$' | sort)
Focused test run: 26 selected test files (root lock skipped)
26 test files, 2598 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Passed: emitted `field_count=5`, `alternate_name_review_field=Review label for editors`, `mapping_name_review_field=review.label.export`, `wordpress_label_from_alternate_name=Review label for editors`, `alternate_mapping_names_review_only=true`, and all execution flags false.

## Status Delta

- `phpPass` moves `1239 -> 1240`.
- `wordpressScenarios` moves `1211 -> 1212`.
- Added 1 focused PASS case and expanded the existing WordPress AcroForm fields smoke.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, token-aware AcroForm key parsing, generation-exact field references, indirect `/Fields` and `/Kids` arrays, indirect widget `/Rect` and `/F` operands, AcroForm action review, XFA/signature review, field hierarchy value inheritance, `/MaxLen` review, widget appearance state, or link/markup annotation promotion. The bounded behavior is AcroForm field alternate-name and mapping-name review metadata before WordPress form handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary tokenizer, string decoder, generation guard, field hierarchy builder, text extractor, and existing WordPress smoke harness. Full upstream model/OCR/rendering parity remains intentionally out of scope under the no-GPU markerPDF directive.
