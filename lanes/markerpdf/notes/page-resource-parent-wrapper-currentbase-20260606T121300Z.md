# Page Resource Parent Wrapper Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T121300Z`
Base accepted HEAD: `259f1bb48b87b09ee9889b2d9331db2eb82715fb`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to native PDF parser layers before OCR/model stages. At this no-GPU boundary, page-tree traversal must resolve indirect references generation-exactly. This slice maps the PDF page-tree contract that a page `/Parent` reference can resolve through an indirect wrapper to the real `/Pages` node before inherited `/Resources` font, XObject, and review metadata lookup.

## Behavior

- `PdfTextExtractor` now resolves page `/Parent` references through `resolvedPageTreeReference()` before validating that the parent is a `/Pages` object.
- `PdfPagePropertyExtractor` uses the same wrapper-aware parent resolution, so boundary metadata reports the real `/Pages` resource owner instead of dropping inherited resources.
- Wrapper object `20 0 R -> 2 0 R` is not reported as the resource owner; the actual `/Pages` object `2 0 R` owns inherited `/Resources 10 0 R`.
- Visible WordPress paragraphs include inherited font text and inherited Form XObject text, while resource names, CMap names, and wrapper internals remain out of visible text.

## Red First

Before the source edit, after adding `PdfPageResourceParentWrapperCurrentBaseTest.php`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect page Parent wrappers before inherited resource lookup
Expected: ['Parent wrapper inherited font text', 'Parent wrapper inherited form text']
Actual: ['A']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect page Parent wrappers before inherited resource lookup
1 test files, 16 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceProcSetInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 440 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-wrapper-currentbase.php
```

Smoke flags: `parent_wrapper_resolved=true`, `resource_owner_object=2`, `resource_object=10`, `resource_inherited=true`, `wrapper_object_not_resource_owner=true`, `visible_text_excludes_resource_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog `/Pages` wrapper traversal, page-tree `/Kids` wrapper traversal, generation-mismatched `/Kids`, generation-mismatched `/Parent`, parent `/Kids` validation, duplicate catalog Kids parent selection, null/empty `/Resources`, exact resource generation selection, category stream fail-closed behavior, or Form XObject null-resource inheritance. The bounded behavior is only indirect wrapper resolution on the page `/Parent` pointer before inherited page `/Resources` lookup and page-boundary review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation-aware object owner map, page-tree reference resolver, resource dictionary resolver, text extractor, page-boundary metadata extractor, and WordPress smoke path. Live OCR, PDFium raster rendering, Surya/Texify/Torch model execution, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
