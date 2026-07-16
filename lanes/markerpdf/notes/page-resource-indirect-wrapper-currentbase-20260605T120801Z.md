# markerPDF page resource indirect wrapper current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T120801Z`

## Source Truth

Upstream markerPDF relies on PDF parser layers for searchable-PDF text before OCR/model fallbacks. At this native no-GPU boundary, inherited page `/Resources` and resource category dictionaries are ordinary PDF objects, so an indirect object may wrap another indirect reference before reaching the final dictionary. The PHP parser must resolve those exact references before font ToUnicode maps, Form XObject invocation, marked-content `/ActualText`, and WordPress paragraph rendering.

## Implementation

- `PdfTextExtractor` now recursively unwraps generation-exact resource dictionary references for page `/Resources` and nested resource categories such as `/Font`, `/XObject`, and `/Properties`.
- `PdfPagePropertyExtractor` now uses the same recursive exact-reference resolution for page-resource review metadata, stream-object fail-closed checks, inherited `null` resources, and subdictionary name collection.
- Cyclic, unresolved, stream-backed, or malformed wrappers still fail closed instead of falling back to stale raw glyph text or arbitrary stream scanning.

## Evidence

Red-first focused run before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL resolves indirect resource dictionary and category wrappers before inherited page text extraction
Actual: array (
  0 => 'A',
  1 => 'B',
)
1 test files, 149 assertions, 1 failures
```

Focused run after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 162 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-indirect-wrapper-currentbase.php
indirect_resource_wrapper_resolved=true
font_category_wrapper_resolved=true
xobject_category_wrapper_resolved=true
properties_category_wrapper_resolved=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Additional broad lane check:

```text
php tools/run-tests.php lanes/markerpdf/tests
532 test files, 31939 assertions, 3 failures
```

The three broad-lane failures are outside this page-resource slice and were reproduced from a clean temporary archive of accepted `HEAD` with the same selected tests:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
3 test files, 7 assertions, 3 failures
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF object parser, exact generation tracking, page-tree resource inheritance path, and WordPress smoke harness. GPU/model OCR, Surya/Texify/Torch execution, pypdfium rendering, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted page-tree parent/kid generation checks, top-level `/Resources null`, indirect `null` resources, explicit empty resource dictionaries, escaped `/Kids`, malformed resource stream boundaries, Form XObject null-resource inheritance, xref `/Prev` trailer-root selection, or the recent font-width advance boundary. The bounded behavior is specifically recursive indirect wrapper resolution for inherited page resource dictionaries and their resource categories before text extraction and review metadata.
