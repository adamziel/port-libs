# markerPDF AcroForm fields page-widget Parent boundary current base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T141818Z`

Base accepted HEAD: `ecd1b761b52dbc5a61bfd1d229f03aa92b48947e`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, AcroForm fields are native searchable-PDF parser and review metadata behavior.
- PDF AcroForm field hierarchies use `/Kids` plus optional child `/Parent` links. Page-owned Widget annotation repair may infer omitted field branches, but an inferred branch with an explicit `/Parent` must still be owned by that parent field's `/Kids` tree.
- PDF form actions, JavaScript, appearance rendering, Python models, OCR, PDFium rendering, and external PDF tools are not executed.

## Implementation

- `PdfAcroFormExtractor::fieldReferencesWithPageWidgetBoundaries()` now validates inferred page-widget field candidates with `fieldCandidateAllowsPageWidgetRepair()`.
- An inferred page-widget candidate without `/Parent` remains valid as a standalone field/widget boundary.
- An inferred candidate with an explicit `/Parent`, including escaped `/Par#65nt`, is accepted only when the referenced parent owns the candidate through its `/Kids` tree.
- Added `PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-acroform-fields-page-widget-parent-boundary-currentbase.php`.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds page widget AcroForm parent repair by escaped Parent ownership before WordPress field review
Values are not identical
Expected: array (
  0 => 'valid.first',
  1 => 'valid.second',
)
Actual: array (
  0 => 'valid.first',
  1 => 'valid.second',
  2 => 'spoof.child',
)
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds page widget AcroForm parent repair by escaped Parent ownership before WordPress field review
1 test files, 53 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCharacteristicsCurrentBaseTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1811 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-page-widget-parent-boundary-currentbase.php
```

The smoke emits `field_names=["valid.first","valid.second"]`, `escaped_parent_valid_branch_preserved=true`, `escaped_parent_mismatch_decoy_excluded=true`, `spoof_value_review_excluded=true`, `visible_text_imported=true`, `form_values_visible_text_excluded=true`, and all form-action/model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior cases: `1903 -> 1904`.
- WordPress scenarios: `1721 -> 1722`.
- Added 53 focused assertions in the new test file.

## Non-Overlap

This does not repeat accepted AcroForm `/Fields` token parsing, page-widget repair, direct-widget field normalization, parent ownership inheritance, child branch repair, field overlap deduplication, generation filtering, object-stream field expansion, comment-reference parsing, wrong-page `/P` rejection, or widget appearance/action review.

The bounded behavior is only escaped `/Parent` ownership validation for field branches inferred from page-owned Widget annotations before WordPress AcroForm review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, AcroForm field tree traversal, page annotation Widget map, form review metadata path, and WordPress smoke path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated and intentionally out of scope for this no-GPU parser slice.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around forms, annotations, fonts/CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
