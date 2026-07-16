# markerPDF page resource entry wrapper current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T135504Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T135504Z`
Base accepted HEAD: `c3f58029a60723af2704c75d84b8e5f448630194`

## Source Truth

Upstream markerPDF relies on PDF parser layers to supply searchable page text before OCR/model stages. At this native no-GPU boundary, inherited page `/Resources` entries are PDF object values: a resource entry such as `/Fwrapped 14 0 R` may resolve to another exact indirect reference before reaching the final Font, Form XObject, or marked-content property dictionary. WordPress paragraph extraction must resolve that resource entry chain without scanning raw glyph names or resource object payloads as visible text.

## Behavior

`PdfTextExtractor` now resolves generation-exact resource entry wrappers when:

- building page/font ToUnicode maps from inherited `/Resources /Font` entries;
- expanding Form XObjects invoked through inherited `/Resources /XObject` entries;
- resolving inherited `/Resources /Properties` entries for `/ActualText` and `/Alt`.

Exact image/XObject review rows still preserve their original referenced object body; the executable Form lookup is the only XObject path that unwraps entry references for invocation.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL resolves inherited resource entry wrappers before text and form lookup
Expected: ["Wrapped entry inherited font text","Wrapped entry actual text","Wrapped entry form text"]
Actual: ["A","B"]
1 test files, 170 assertions, 1 failures
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 183 assertions, 0 failures
```

Adjacent resource/image checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
12 test files, 624 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php
5 test files, 823 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 628 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-entry-wrapper-currentbase.php
wrapped_font_entry_resolved=true
wrapped_xobject_entry_resolved=true
wrapped_properties_entry_resolved=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, parent/Kids validation, generation-mismatched page Resources fail-closed behavior, indirect resource dictionary/category wrappers, stream-valued resource category rejection, Form XObject null-resource inheritance, image XObject exact-generation review rows, page Contents non-inheritance, xref repair, or PageLabels work. The bounded behavior is only inherited resource entry references that wrap another exact resource object reference before font/Form/properties lookup.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware resource reference resolver, inherited page resource lookup, CMap/font maps, Form XObject expansion, marked-content replacement, page-boundary metadata, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, and exact upstream model benchmark parity remain intentionally out of scope under the markerPDF no-GPU directive.
