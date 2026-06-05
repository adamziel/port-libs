# markerPDF Page Resource Kids Generation Current Base

Lane: `markerpdf`
Slice: `markerpdf-page-resource-inheritance-current-base-20260605T052503Z`
Base accepted HEAD: `300ec9ba84c673261512ddb2a6bb27d7aede632d`

## Source Truth

Upstream markerPDF delegates searchable PDF page extraction to PDF parser layers before OCR/model stages. At this native no-GPU boundary, PDF indirect references are generation-qualified `object generation R` operands. Page-tree `/Kids` traversal must therefore resolve the exact referenced generation before a leaf page can inherit ancestor `/Resources`, and a malformed page tree must not fall back to scanning arbitrary streams as visible WordPress paragraphs.

## Behavior

- `PdfTextExtractor` now carries the catalog `/Pages` generation and each `/Kids` child generation through page-tree traversal.
- `PdfTextExtractor` blocks all-stream fallback when a catalog page tree exists but all child references are missing or generation-mismatched.
- `PdfPagePropertyExtractor` now uses the same generation-exact page-tree traversal for page-boundary resource review metadata.
- A stale child `3 0 obj` no longer satisfies `/Kids [3 1 R]`; valid sibling leaves still inherit the ancestor `/Resources` font map.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL skips generation-mismatched page tree Kids before inherited resource lookup
Expected: 1
Actual: 2
1 test files, 97 assertions, 1 failures
```

Separate red probe before the all-stream fallback guard showed a catalog page tree with only `/Kids [3 1 R]` leaking stale object `3 0` text:

```text
array (
  0 => 'Stale kid page leak',
)
```

Focused run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 108 assertions, 0 failures
```

Adjacent page-resource/text/property run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
9 test files, 1109 assertions, 0 failures
```

Xref generation family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php
9 test files, 120 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-kid-generation-currentbase.php
```

The smoke emits `valid_sibling_inherits_resources=true`, `stale_kid_generation_excluded=true`, `all_stale_page_tree_blocks_fallback=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, top-level `/Resources null`, indirect null `/Resources`, explicit empty dictionaries, malformed `/Resources` fail-closed behavior, generation-mismatched page `/Parent`, generation-mismatched resource entries, escaped `/Type` names, Form XObject omitted/null `/Resources` inheritance, page `/Contents` non-inheritance, xref object-stream generation repair, PageLabels generation work, or attachment/form/image/metadata slices. The bounded behavior is only generation-exact catalog `/Pages` and page-tree `/Kids` traversal before inherited resource lookup and fallback stream scanning.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware object inventory, page-tree walker, resource dictionary resolver, Type0 CMap/font map extraction, page-boundary resource metadata, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
