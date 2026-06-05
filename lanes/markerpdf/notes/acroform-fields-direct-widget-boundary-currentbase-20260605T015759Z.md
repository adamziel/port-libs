# markerPDF AcroForm Direct Widget Fields Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T015759Z`
Base accepted HEAD: `a5ec9ff86bd1a52891911ed457b520a215e6d13b`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed `pdftext`/PDFium extraction before OCR/model stages. The native no-GPU PHP lane owns the dependency boundary where AcroForm field dictionaries and page Widget annotations are parsed into review metadata before WordPress import.

Relevant PDF parser behavior: AcroForm terminal fields can have Widget annotation kids, and Widget annotations can point back to their owning field through `/Parent`. If a malformed but common producer lists a pure Widget annotation directly in catalog `/AcroForm /Fields`, the form review should normalize that reference to the owning field root instead of treating the widget as an unnamed field or dropping it. Field values, alternate labels, and mapping names remain review metadata and do not become visible PDF text.

No OCR, Surya, Texify, Torch, PDFium rendering, model worker, browser, form-action execution, JavaScript, or external PDF tool was executed.

## Behavior

`PdfAcroFormExtractor` now normalizes explicit `/AcroForm /Fields` references before field-tree traversal:

- pure Widget references with valid `/Parent` field chains are replaced by the root field candidate;
- merged field/widget dictionaries remain self-owned;
- pure widgets without a valid parent remain excluded as before;
- existing page-owned widget repair still promotes omitted page-visible parent fields and standalone widget fields without promoting detached page-unreferenced widgets.

The focused fixture lists widgets `8 0 R` and `14 0 R` directly in `/Fields`; those widgets point to `direct.widget.parent` and nested `direct.group.child` parent fields. The patch recovers field objects `6` and `12`, keeps widget objects `8` and `14` in page annotation order, excludes a detached widget-parent decoy, and keeps all form values out of visible page text.

## Red-First Evidence

Before implementation, the direct-widget `/Fields` boundary dropped the form field:

```text
php -r '... direct widget /Fields fixture ...'
[]
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS normalizes direct widget entries in AcroForm Fields to their parent field roots
PASS uses token aware AcroForm field keys before WordPress review metadata
PASS rejects generation mismatched AcroForm field and page widget references
PASS keeps exact nonzero generation AcroForm fields before stale same object decoys
PASS resolves indirect AcroForm Fields and Kids arrays before WordPress field review
PASS resolves indirect AcroForm widget Rect and F operands before WordPress field review
PASS uses token aware AcroForm reference arrays before WordPress field review
PASS preserves AcroForm alternate and mapping names as review metadata only

1 test files, 245 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 2454 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-widget-currentbase.php
```

Emitted `field_names=["direct.widget.parent","direct.group.child"]`, `field_objects=[6,12]`, `widget_objects=[[8],[14]]`, `direct_widget_parent_normalized=true`, `nested_widget_parent_normalized=true`, `detached_widget_parent_excluded=true`, `form_values_visible_in_text=false`, `need_appearances=true`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, token-aware escaped `/Fields` parsing, indirect `/Fields` and `/Kids` arrays, generation-exact field refs, alternate `/TU` and `/TM` review, widget appearance state, submit/reset actions, signature/XFA review, page annotation widget link promotion, or security permission slices. The bounded behavior is specifically explicit `/AcroForm /Fields` entries that point at pure Widget annotations whose `/Parent` field roots own the actual field values and hierarchy.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation-valid reference checks, dictionary/array parser, page widget map, field hierarchy builder, action walker, and WordPress smoke path. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
