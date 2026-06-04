# markerPDF AcroForm Indirect Field Array Boundary

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260604T231422Z`
Base accepted HEAD: `fd0f5327abfd3b58715219a1c13c4c8295941253`

## Source Truth

Upstream markerPDF relies on PDF parser layers before conversion. This native no-GPU slice maps a PDF parser boundary for AcroForm field trees: `/AcroForm /Fields` and terminal/non-terminal field `/Kids` entries are arrays, and those arrays may be stored as direct arrays or valid indirect array objects. Field values remain review metadata for WordPress import and must not become visible page text.

No OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, model worker, browser, live service, or external PDF tool was executed.

## Behavior

`PdfAcroFormExtractor` now resolves generation-checked indirect array objects for:

- catalog AcroForm `/Fields`;
- field dictionary `/Kids` arrays at each field-tree level.

The resolver remains bounded to array objects and returns null for missing, malformed, cyclic, or non-array references. Existing direct-array, page-owned widget promotion, token-aware key parsing, object-generation matching, action review, XFA, signature, appearance, and value-state behavior is preserved.

The focused fixture proves:

- a metadata-only field listed only through indirect `/Fields 20 0 R` is preserved;
- a field with `/Kids 21 0 R` binds its widget;
- a nested non-terminal field with indirect `/Kids` arrays keeps the full `profile.name` field name and inherited value;
- an unattached indirect array decoy remains excluded;
- review values do not enter extracted visible text.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS uses token aware AcroForm field keys before WordPress review metadata
PASS rejects generation mismatched AcroForm field and page widget references
PASS keeps exact nonzero generation AcroForm fields before stale same object decoys
FAIL resolves indirect AcroForm Fields and Kids arrays before WordPress field review
Expected: [article.indirect, metadata.hidden, profile.name]
Actual: [article.indirect, name]
1 test files, 74 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS uses token aware AcroForm field keys before WordPress review metadata
PASS rejects generation mismatched AcroForm field and page widget references
PASS keeps exact nonzero generation AcroForm fields before stale same object decoys
PASS resolves indirect AcroForm Fields and Kids arrays before WordPress field review
1 test files, 100 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 2275 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-arrays-currentbase.php
```

The smoke emits `field_names=["article.indirect","metadata.hidden","profile.name"]`, `metadata_only_field_preserved=true`, `nested_value_source="field_hierarchy_inherited"`, `detached_indirect_decoy_excluded=true`, `visible_text_contains_form_value=false`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, token-aware AcroForm key parsing, generation-exact field references, field hierarchy value inheritance, widget appearance state, submit/reset action review, seed/lock/signature review, XFA value/action review, or page annotation widget link promotion. The bounded behavior is only indirect array object resolution for AcroForm `/Fields` and field `/Kids` before existing field review.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation guard, dictionary/array parser, page widget map, field hierarchy builder, action walker, text extractor, and WordPress smoke renderer. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF directive.
