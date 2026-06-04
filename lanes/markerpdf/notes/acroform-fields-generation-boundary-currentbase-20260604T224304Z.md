# markerPDF AcroForm field generation boundary current base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260604T224304Z`
Base accepted HEAD: `3b9694d1bdbef3af745fe21f14add747137f6280`

## Source Truth

Upstream markerPDF delegates searchable PDF form/page parsing to PDF parser layers before converting review metadata into document content. At this native no-GPU boundary, indirect PDF references carry object number and generation (`N G R`). AcroForm `/Fields`, field `/Kids`, page Widget annotations, Widget `/Parent`, catalog `/AcroForm`, and page-tree `/Kids` must not bind to stale same-object-number bodies whose generation does not match the reference.

No OCR, Surya, Texify, Torch/model worker, pypdfium rendering, browser, JavaScript, form action, signature validation, or external PDF tool was executed.

## Behavior

`PdfAcroFormExtractor` now records the selected generation for scanned direct objects and uses generation-checked references for AcroForm field discovery and page-widget repair:

- `/AcroForm /Fields [6 1 R]` is unresolved when only `6 0 obj` exists, so stale field values are not imported.
- Page `/Annots [8 1 R]` does not promote a stale `8 0 obj` Widget or its stale `/Parent` field.
- Exact nonzero fields/widgets such as `6 1 R` and `8 1 R` remain importable even when later stale generation-zero decoys reuse the same object numbers.
- Returned field/widget `object` metadata remains object-number compatible, while action/rendering flags stay false.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS uses token aware AcroForm field keys before WordPress review metadata
FAIL rejects generation mismatched AcroForm field and page widget references
Expected: []
Actual: ['stale.generation.listed', 'stale.page.widget.parent']
FAIL keeps exact nonzero generation AcroForm fields before stale same object decoys
Expected: ['current.generation.email']
Actual: ['stale.generation.email']
1 test files, 50 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS uses token aware AcroForm field keys before WordPress review metadata
PASS rejects generation mismatched AcroForm field and page widget references
PASS keeps exact nonzero generation AcroForm fields before stale same object decoys
1 test files, 73 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 2248 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-generation-boundary-currentbase.php
```

Emitted `generation_mismatch_field_count=0`, `exact_generation_field_names=["current.generation.email"]`, `uses_current_generation_field=true`, `excludes_stale_generation_fields=true`, `visible_text_contains_form_value=false`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, token-aware `/Fields` key parsing, field hierarchy value inheritance, widget appearance/current-state review, submit/reset action review, XFA/signature widget review, outline generation boundaries, xref generation repair, or page annotation widget link promotion. The bounded behavior is specifically generation-exact AcroForm field and widget reference resolution before WordPress form-review metadata is emitted.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP object scanner, dictionary/array parser, page tree walker, page annotation widget map, field hierarchy builder, action walker, and WordPress smoke path. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF directive.
