# markerPDF AcroForm detached owner boundary current-base

Session: `port-dev-markerpdf-acroform-fields-20260608T200330Z`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T200330Z`
Base accepted HEAD: `04e99b68d5dc6e073f4bb0aa436e72dabb16d510`

## Source Truth

Upstream `sddai/markerPDF` consumes searchable PDF parser output before OCR/model stages. Under the current no-GPU markerPDF scope, this lane owns native AcroForm field-tree boundaries that determine which field values are safe to expose as WordPress review metadata without executing PDF actions, JavaScript, OCR, PDFium, or external tools.

PDF AcroForm `/Fields` roots define the reachable field tree. Parentless child dictionaries are common in compact PDFs, but a detached field-like object elsewhere in the object graph must not become an owner just because it also lists the child in `/Kids`.

## Change

`PdfAcroFormExtractor` now scopes parentless-child owner counting to the reachable AcroForm field tree. A loose field-tree walk records objects reachable from catalog `/AcroForm /Fields` plus repaired page-widget roots, and `parentlessFieldChildOwners()` ignores detached field-like dictionaries outside that scope.

Before this patch, a fixture with `/Fields [6 0 R]`, reachable parent `6 -> Kids [12]`, child `12 -> Kids [14]`, and detached object `80 -> Kids [12]` produced only the parent field:

```text
Expected: ['profile.email']
Actual:   ['profile']
```

After the patch, the reachable child field `profile.email` is preserved with hierarchy path `[6, 12]`, widget `[14]`, inherited parent attributes, and detached owner metadata excluded from both form review JSON and visible WordPress text.

## Verification

Red-first focused run before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDetachedOwnerBoundaryCurrentBaseTest.php
1 test files, 1 assertions, 1 failures
```

Focused run after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDetachedOwnerBoundaryCurrentBaseTest.php
1 test files, 43 assertions, 0 failures
```

Adjacent AcroForm ownership family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsSharedChildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
3 test files, 645 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-detached-owner-currentbase.php
```

The smoke reports `field_names=["profile.email"]`, `hierarchy_path_objects=[6,12]`, `detached_owner_excluded=true`, `detached_owner_value_excluded=true`, `visible_text_preserved=true`, `field_values_hidden_from_visible_text=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm duplicate `/Fields` key handling, catalog AcroForm duplicate pointer handling, indirect array-object boundaries, tailed array-object rejection, explicit `/Parent` ownership checks, shared reachable parentless-child rejection, page-widget `/P` ownership repair, direct widget/materialized dictionary boundaries, XFA/signature/action review, annotations, metadata, xref repair, CMaps, images, stream filters, OCR, or model handoffs.

The bounded behavior is only detached field-like dictionaries outside the reachable AcroForm field tree no longer counting as parentless-child owners.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/array/reference parser, AcroForm field-tree extractor, page-widget repair path, and WordPress smoke path. GPU/model/OCR execution, Surya/Torch/Texify, PDFium/pypdfium runtime execution, Streamlit/FastAPI model workers, JavaScript/form action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around forms, annotations, metadata, fonts/CMaps, stream filters, xref repair, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
