# markerPDF AcroForm Choice Top Index Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T184338Z`
Session: `port-dev-markerpdf-acroform-fields-20260606T184338Z`
Base accepted HEAD: `d58a45056308ade34ea13cdabb81f621d495fada`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF parser extraction through static PDF structure before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF AcroForm boundary from PDF choice fields: `/TI` is a choice-field top-index/scroll-position operand and must be preserved as review metadata, including inherited parent-field values and exact-generation scalar references, without changing the imported field value or visible text.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfAcroFormExtractor` now inherits `/TI` alongside `/FT`, `/Ff`, `/V`, `/Opt`, `/I`, `/Q`, and the existing form-field attributes.
- Choice fields expose `choice_top_index_review` and matching `value_state` keys for resolved, invalid/out-of-range, inherited, terminal, and generation-mismatched unresolved `/TI` operands.
- The top-index review maps a valid top option to its export and label but marks it review-only; the importer still uses `/V` and `/I` for current selected values.
- `/TI`, `/Opt`, and choice labels stay out of visible WordPress text and do not execute actions, JavaScript, appearance streams, rendering, Python, models, or external PDF tools.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceTopIndexBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "choice_top_index" in lanes/markerpdf/tests/PdfAcroFormFieldsChoiceTopIndexBoundaryCurrentBaseTest.php on line 60
FAIL reviews AcroForm choice TI top index inheritance before WordPress field import
Values are not identical
Expected: 2
Actual: NULL
1 test files, 9 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceTopIndexBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews AcroForm choice TI top index inheritance before WordPress field import
1 test files, 65 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 53 selected test files (root lock skipped)
53 test files, 3989 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-choice-top-index-currentbase.php
Emits inherited_top_index=2, inherited_top_label=Published, inherited_source_boundary=field_hierarchy_inherited, out_of_range_marked_invalid=true, generation_mismatch_marked_unresolved=true, choice_top_index_used_for_import=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2645 -> 2646`.
- `wordpressScenarios`: `2238 -> 2239`.
- New focused file: `PdfAcroFormFieldsChoiceTopIndexBoundaryCurrentBaseTest.php` adds 1 PASS case and 65 assertions.
- New WordPress smoke: `wordpress-pdf-acroform-fields-choice-top-index-currentbase.php`.
- No new mapped manifest denominator claim.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware scalar resolver, AcroForm field tree traversal, inherited attribute merger, choice option normalizer, widget/page boundary repair, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted AcroForm widget/page repair, token-aware `/Fields` and `/Kids` parsing, xref generation selection, object-stream field expansion, duplicate Annots/Parent handling, quadding `/Q`, field flags, choice `/Opt` selected values, SubmitForm/ResetForm, rich text `/RV`, XFA boundaries, signature/seed/lock review, calculation/action review, widget appearance state, annotation resources, outline/navigation, PageLabels, encrypted preflight, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only `/TI` choice-field top-index review before WordPress field import.
