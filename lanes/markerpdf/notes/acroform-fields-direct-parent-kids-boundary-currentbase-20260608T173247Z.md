# markerPDF AcroForm Direct Parent Kids Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T173247Z`

Base accepted HEAD: `bb0155ef4ba8e70b3abc02eb190fa91b5dd44102`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF parser output and review metadata before OCR/layout/model stages. Under the current no-GPU markerPDF scope, AcroForm field-tree and Widget annotation boundaries are native PDF parser behavior for WordPress import review.

PDF AcroForm widgets can carry a direct `/Parent` field dictionary. Existing native coverage handled direct parent dictionaries without `/Kids`, explicit empty `/Kids` rejection, direct page widgets, direct `/Fields` and `/Kids` dictionary materialization, and canonical direct Widget Kids matching for indirect parent fields. This slice covers the narrower boundary where the direct `/Parent` field dictionary itself contains direct `/Kids` Widget dictionaries. The direct kid can be equivalent to the page-owned Widget after the page Widget's `/Parent` is rewritten to the synthetic parent object, so `/Parent` must be ignored only for that synthetic direct-child comparison.

## Red-First Evidence

Before the source edit, the focused test failed because the direct `/Parent` field was rejected when its direct `/Kids` widget could not be matched back to the page annotation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL matches direct Parent field Kids widget dictionaries to the page-owned widget (lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentKidsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'direct.parent.kids',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor` now uses field-scoped widget equivalence in both field-tree containment and duplicate page-widget replacement.
- Synthetic direct Widget Kids are compared to the page annotation Widget with `/Parent` ignored only when the synthetic kid was materialized from that exact parent field's `/Kids` array.
- Wrong-parent direct Kids widgets remain rejected: an explicit `/Parent 99 0 R` on the synthetic direct kid does not match the synthetic direct parent.
- Added a focused PDF fixture with one accepted direct parent/kids field and one wrong-parent direct-kid decoy.
- Added a WordPress smoke that emits review-only field metadata and verifies no form actions, JavaScript, Python/model code, or external PDF tools execute.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS matches direct Parent field Kids widget dictionaries to the page-owned widget
1 test files, 40 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectChildParentWidgetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsSharedChildBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 324 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroForm*CurrentBaseTest.php
Focused test run: 88 selected test files (root lock skipped)
88 test files, 4888 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-kids-currentbase.php
```

The smoke exits 0 and emits `page_widget_referenced=true`, `wrong_parent_direct_kid_excluded=true`, `field_values_review_only=true`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary/array parser, direct AcroForm dictionary materializer, page Widget annotation map, field-tree ownership walker, field hierarchy/value review logic, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, form action execution, JavaScript execution, signing, decryption, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted direct page Widget materialization, direct parent dictionaries without `/Kids`, direct Widget `/Fields` parent-without-`/Kids` normalization, parent ownership rejection, direct child parent Widget handling, canonical direct Widget Kids matching for indirect parent fields, shared child rejection, tailed field arrays/dictionaries, duplicate parent/page keys, generation-exact field references, object-stream field recovery, XFA/signature/action review, submit/reset review, default-resource appearance metadata, or supplied table/equation boundaries. The bounded behavior is only page-owned Widgets whose direct `/Parent` field dictionary declares direct `/Kids` Widget dictionaries that must match the owning page annotation.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around forms, annotations, fonts, CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
