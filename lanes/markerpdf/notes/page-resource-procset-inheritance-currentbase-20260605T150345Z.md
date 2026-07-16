# markerPDF page-resource ProcSet inheritance current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T150345Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T150345Z`
Base accepted HEAD: `5e277f7985f08bbea655de828433799334fd1a1e`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to `pdftext.dictionary_output()`/PDF parser layers before OCR and model stages. At this native no-GPU parser boundary, PDF page `/Resources` are inheritable page-tree attributes and `/ProcSet` is a resource dictionary array of procedure-set names. WordPress import should preserve those inherited names as page review metadata while keeping them out of visible Gutenberg paragraph text.

## Behavior

- `PdfPagePropertyExtractor` now reports `procset_names` for the effective inherited page resource dictionary.
- Direct arrays such as `[/PDF /Text /ImageB /Image#43]` preserve decoded name order and de-duplicate repeated names.
- Indirect and wrapped ProcSet arrays resolve through the same generation-exact value resolver used by other page-resource review metadata.
- Visible text remains driven by inherited `/Font` resources; `/ProcSet` names do not become fallback page text.

## Evidence

Red-first focused run after adding the new assertion and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetInheritanceCurrentBaseTest.php
FAIL reports inherited page ProcSet arrays without leaking resource names into WordPress text
Expected: array (
  0 => 'PDF',
  1 => 'Text',
  2 => 'ImageB',
  3 => 'ImageC',
)
Actual: NULL
1 test files, 11 assertions, 1 failures
```

Focused run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetInheritanceCurrentBaseTest.php
1 test files, 20 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php
12 test files, 395 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-procset-inheritance-currentbase.php
```

The smoke emits `direct_procset_array_inherited=true`, `indirect_procset_array_inherited=true`, `resource_names_excluded_from_paragraphs=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, catalog `/Kids` path recovery, parent/Kids generation checks, null or malformed `/Resources` handling, direct resource-entry filtering, indirect resource/category wrappers, stream-valued category rejection, resource entry wrappers, image XObject inheritance review, Form XObject null-resource inheritance, or page `/Contents` non-inheritance. The bounded behavior is only inherited `/Resources /ProcSet` array review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page-tree resource inheritance resolver, generation-exact indirect value resolver, page-boundary metadata extractor, Type0 CMap/font lookup, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
