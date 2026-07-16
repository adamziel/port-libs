# markerPDF Page Resource Kids Path Inheritance Current Base

Lane: `markerpdf`
Slice: `markerpdf-page-resource-inheritance-current-base-20260605T105420Z`
Base accepted HEAD: `82babcc28b3524f3f387fea16306591bd14fd892`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to PDF parser layers before OCR/model stages. At this native no-GPU boundary, the catalog `/Pages` tree is the selected page source. When a page is reachable through that `/Kids` tree but omits its leaf `/Parent`, inherited `/Resources` should still follow the selected catalog path instead of falling back to raw streams, root resource decoys, or model execution.

## Behavior

- `PdfTextExtractor` now recovers page lineage from the selected catalog `/Kids` traversal path only when the current page-tree object omits `/Parent`.
- Explicit malformed or mismatched `/Parent` references remain governed by the existing fail-closed parent and Kids validation.
- `PdfPagePropertyExtractor` uses the same recovered catalog-path lineage so page-boundary review metadata reports the nearest `/Pages` resource owner selected for text extraction.
- The WordPress smoke proves the intermediate `/Pages /Resources 20 0 R` font and Form XObject are selected while root-level `/Resources 30 0 R` decoys stay uninvoked.

## Evidence

Red-first focused run after adding the assertion and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL inherits resources from the catalog Kids path when a reachable page omits Parent
Expected: array (
  0 => 'Catalog path inherited font text',
  1 => 'Catalog path inherited form text',
)
Actual: array (
  0 => 'A',
)
1 test files, 135 assertions, 1 failures
```

Focused run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 148 assertions, 0 failures
```

Adjacent page-resource/text family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
13 test files, 1231 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-kids-path-inheritance-currentbase.php
```

The smoke emits `catalog_kids_path_inherits_resources=true`, `nearest_pages_resource_owner_selected=true`, `nearest_resource_object_selected=true`, `root_resource_decoy_excluded=true`, `root_xobject_decoy_uninvoked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, top-level `/Resources null`, indirect null `/Resources`, explicit empty dictionaries, malformed `/Resources` fail-closed behavior, generation-mismatched page `/Parent`, parent `/Kids` mismatch fail-closed behavior, generation-mismatched page `/Kids`, generation-mismatched resource entries, escaped `/Kids` or `/Type` names, Form XObject omitted/null `/Resources` inheritance, page `/Contents` non-inheritance, page resource stream-category boundaries, xref repair, attachments, forms, image filter metadata, metadata, or OCR/model handoffs. The bounded behavior is only selected catalog `/Kids` path resource inheritance when a reachable page tree node omits `/Parent`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact-generation object inventory, catalog page-tree walker, resource dictionary resolver, Type0 CMap/font map extraction, Form XObject expansion path, page-boundary resource metadata, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
