# markerPDF Page Resource Escaped Kids Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T055824Z`

Base accepted HEAD: `f5c73404b6a6f2a54f818e5e86fd411622e547ba`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through PDF parser layers before OCR/model stages. At this native no-GPU boundary, PDF page-tree keys are ordinary PDF names, so escaped names such as `/Ki#64s` must resolve as `/Kids`, and only the top-level page-tree `/Kids` array may select leaf pages before inherited `/Resources` supply fonts and XObjects.

## Implementation

- `PdfTextExtractor::pageTreeKidObjectReferences()` now resolves the top-level `Kids` value through the token-aware dictionary reader used for escaped resource names, then resolves either direct or indirect Kids arrays.
- `PdfPagePropertyExtractor` now uses top-level dictionary lookup for page-tree `Kids`, resource `Resources`, and object `Type` validation so nested review dictionaries cannot hide or replace page-tree resource metadata.
- `PdfPagePropertyExtractor::pdfObjectTypeName()` now distinguishes full object bodies from already-extracted dictionary bodies before scanning for `/Type`, preventing nested `/PieceInfo` dictionaries from shadowing page-tree node type checks.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses escaped top-level Kids instead of nested decoy Kids before inherited resource lookup
FAIL resolves escaped indirect Kids arrays before inherited resource lookup
1 test files, 2 assertions, 2 failures
```

The first failure followed a nested `/PieceInfo ... /Kids [99 0 R]` decoy and imported `Nested decoy kid resource leak`. The second failed to resolve escaped indirect `/Ki#64s 20 0 R`, yielding no text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses escaped top-level Kids instead of nested decoy Kids before inherited resource lookup
PASS resolves escaped indirect Kids arrays before inherited resource lookup
1 test files, 22 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1131 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-escaped-kids-currentbase.php
```

The smoke emits `escaped_top_level_kids_selected=true`, `nested_decoy_kids_excluded=true`, `indirect_escaped_kids_array_resolved=true`, `resource_inherited=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, generation-exact `/Kids` references, escaped `/Type` names, top-level `/Resources null`, malformed `/Resources` fail-closed behavior, Form XObject resource inheritance, page `/Contents` non-inheritance, resource-entry generation filtering, or page-resource category stream boundaries.

The bounded behavior is escaped top-level page-tree `/Kids` traversal, including indirect Kids arrays, before inherited `/Resources` font and XObject lookup while nested review dictionaries remain non-authoritative.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary readers, page-tree walker, generation-aware reference resolver, inherited resource dictionary resolver, Type0 CMap/font maps, Form XObject expansion, page-boundary review metadata, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
