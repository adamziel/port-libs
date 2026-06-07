# markerPDF AcroForm Fields Indirect Array-Object Boundary Current Base

## Scope

Native no-GPU markerPDF/PDF parser behavior for AcroForm field review.

Upstream source truth: markerPDF consumes searchable PDF parser output before OCR/model stages. PDF AcroForm `/Fields` and terminal field `/Kids` values are arrays or indirect references to arrays. A WordPress import must not treat a referenced dictionary as an array object just because the dictionary contains a nested array with direct field-like dictionaries.

## Change

- `PdfAcroFormExtractor::materializeDirectDictionariesInNamedArray()` now reuses the strict indirect array resolver for referenced `/Fields` and `/Kids` operands.
- Referenced targets must resolve to an actual PDF array object before direct field dictionaries are synthetic-materialized.
- Malformed dictionary targets such as `<< /NotFields [<< ... >>] >>` and `<< /NotKids [<< ... >>] >>` are ignored, while valid page-owned widget repair and terminal parent field review continue.

## Red-First Evidence

Before the source change, a one-off probe with `/Fields 20 0 R` where object `20` was `<< /NotFields [<< /FT /Tx /T (malformed.root.decoy) ... >>] >>` returned:

```text
array (
  0 => 'malformed.root.decoy',
)
```

That showed the materializer was using the first `[` inside the referenced dictionary instead of requiring an array object target.

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectArrayObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires indirect AcroForm Fields targets to be array objects before direct field materialization
PASS requires indirect AcroForm Kids targets to be array objects before child field materialization

1 test files, 62 assertions, 0 failures
```

AcroForm family after fix:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 62 selected test files (root lock skipped)
...
62 test files, 4378 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-array-object-boundary-currentbase.php
```

Reports `malformed_fields_object_decoy_excluded=true`, `malformed_kids_object_decoy_excluded=true`, `valid_page_widget_repair_preserved=true`, `terminal_parent_preserved_after_malformed_kids=true`, `visible_text_excludes_form_values=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF token, array, dictionary, indirect-reference, page-widget, and AcroForm field-tree helpers already present in `lanes/markerpdf/src/PdfAcroFormExtractor.php`. It does not run OCR, Surya, Texify, Torch, pypdfium, external PDF tools, JavaScript, or form actions.

## Non-Overlap

This does not repeat accepted AcroForm root stream rejection, field/widget stream rejection, direct dictionary materialization inside valid array objects, indirect Fields/Kids array chains, object-stream field expansion, duplicate key handling, generation selection, child branch repair, direct widgets, page widget parent repair, XFA/signature/action review, named destinations, annotations, metadata, xref repair, or OCR/model handoffs. The bounded behavior is only indirect `/Fields` and `/Kids` targets that are dictionaries containing nested arrays rather than array objects.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
