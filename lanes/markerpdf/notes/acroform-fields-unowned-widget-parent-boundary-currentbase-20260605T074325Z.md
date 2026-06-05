# markerPDF AcroForm Unowned Widget Parent Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T074325Z`
Base accepted HEAD: `af931574404bdace83039570c6acdb9bd0106ac7`

## Source Truth

Relevant PDF parser behavior for this slice: AcroForm field discovery starts at catalog `/AcroForm /Fields`. Page annotation `/Widget` dictionaries may repair malformed PDFs by pointing to an omitted parent field, but that repair must stay bounded to the field tree that actually owns the widget through `/Kids`. A page annotation whose `/Parent` points at an unrelated field should not import that field value into WordPress review metadata.

This is native searchable-PDF/parser behavior only. No OCR, Surya, Texify, Torch, PDFium rendering, model worker, browser, or external PDF tool was executed.

## Implementation

`PdfAcroFormExtractor` now checks the candidate parent field tree before promoting page-widget parent fields that were omitted from `/AcroForm /Fields`:

- valid omitted fields are still repaired when the parent field `/Kids` tree reaches the current page widget;
- standalone field widgets without `/Parent` are still reviewed as before;
- existing `/Fields` roots can still attach page-only widgets through `/Parent`, preserving accepted signature/XFA widget review behavior;
- unowned `/Parent`-only widget decoys no longer promote unrelated field values.

The WordPress smoke extends the existing AcroForm field-boundary fixture with `unowned.parent`, where the page-listed widget points at a parent field whose `/Kids` contains a different detached widget. The smoke reports `unowned_widget_parent_repair_excluded=true` and keeps all form/action/model execution flags false.

## Red-First Evidence

Before implementation, the new focused case failed because the page-widget parent repair imported `unowned.parent`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects page widget parent repair when the parent field Kids do not own the widget
Expected: array (0 => 'listed.safe', 1 => 'owned.omitted')
Actual: array (0 => 'listed.safe', 1 => 'unowned.parent', 2 => 'owned.omitted')
1 test files, 458 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects page widget parent repair when the parent field Kids do not own the widget
1 test files, 478 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 27 selected test files (root lock skipped)
27 test files, 2789 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Emits `field_count=8`, `unowned_widget_parent_repair_excluded=true`, valid omitted field promotion for `omitted.category`, and all action/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted AcroForm page-owned widget discovery, direct Widget `/Fields` normalization, child-field root normalization, token-aware `/Fields` parsing, generation-exact field refs, indirect `/Fields` and `/Kids` arrays, trailer-root selection, comment-split references, widget appearance state, XFA/signature review, submit/reset actions, or security permission slices. The bounded behavior is only the `/Kids` ownership check before promoting an omitted parent field from a page `/Widget /Parent` reference.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, dictionary parser, page-tree walker, page annotation widget map, field tree traversal, and existing AcroForm value/action review code. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF directive.
