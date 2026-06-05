# markerPDF page resource resolved generation current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T101959Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T101959Z`
Base accepted HEAD: `3a4bdc2dccbffb08c0bcb43152a330884585659f`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through page-scoped native extraction before OCR/model work. At the native no-GPU PHP boundary, page `/Resources` follows PDF page-tree inheritance: omitted or null resources inherit from the nearest valid ancestor, and indirect resource dictionaries must resolve to the exact object generation named by the page-tree resource reference.

## Behavior

`PdfPagePropertyExtractor` now carries the exact resolved `/Resources` object generation into page-boundary metadata. This mirrors the existing image XObject review provenance and keeps WordPress review rows generation-aware when a page inherits a current nonzero-generation resource dictionary.

The focused fixture has an ancestor `/Pages /Resources 10 2 R` plus a stale `10 0 obj` decoy. Native text extraction already selected the current generation for the font map and Form XObject; this slice makes the page-boundary metadata report `resource_generation=2` instead of omitting generation provenance.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php
FAIL reports resolved inherited page resource generation while selecting current resource entries
Expected: 2
Actual: NULL
1 test files, 11 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php
1 test files, 16 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
11 test files, 539 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-resolved-generation-currentbase.php
emits resolved_resource_generation_reported=true, current_generation_resources_selected=true, stale_resource_generation_excluded=true, visible_paragraph_count=2, and model/external-tool flags false.
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, current-object generation cache, page-tree resource resolver, page-boundary metadata extractor, font map resolver, Form XObject expander, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, raster rendering, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted malformed generation-mismatch fail-closed behavior, parent `/Kids` validation, escaped `/Kids` handling, null/empty resource inheritance, stream/category resource blocking, image XObject resource provenance, nested top-level resource-category selection, or Form XObject resource inheritance. The bounded behavior is resolved nonzero-generation `/Resources` provenance in page-boundary review metadata after native extraction has selected the current resource dictionary.
