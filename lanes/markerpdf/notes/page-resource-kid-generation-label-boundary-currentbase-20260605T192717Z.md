# Page Resource Kid Generation Label Boundary Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T192717Z`

## Source Truth

Upstream markerPDF gets page-local text and page metadata from the selected PDF page tree. The native no-GPU path should treat the catalog `/Pages` tree as authoritative: when `/Kids` references only a stale or non-selected page generation, WordPress import metadata must not synthesize page labels from unrelated decoded streams.

## Change

`PdfTextExtractor::extractPageLabels()` now follows the same fallback boundary already used by text extraction: if the selected catalog has a page tree but no live page objects, it returns no labels instead of counting all decoded streams. This prevents a newer same-object-number page body from creating fallback labels when the catalog `/Kids` entry selected an older generation.

Added a focused fixture where:

- the catalog page tree lists `/Kids [3 0 R]`;
- the PDF also contains a newer `3 1 obj` page body with its own stream;
- text extraction, page-resource review, and outline page counts are already blocked;
- page labels must also remain empty.

Added `wordpress-pdf-page-resource-kid-generation-label-boundary-currentbase.php` as a smoke showing the same boundary without Python, models, or external PDF tools.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 189 assertions, 1 failures
FAIL blocks fallback page labels when catalog Kids select a stale page generation
Expected: []
Actual: ['1', '2']
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 193 assertions, 0 failures
```

Owning family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
19 test files, 745 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-kid-generation-label-boundary-currentbase.php
catalog_page_tree_blocks_stream_label_fallback=true
selected_page_count=0
visible_text_blocked=true
page_resource_review_blocked=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-kid-generation-label-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Residual Risk

A broader exploratory run including `lanes/markerpdf/tests/PdfTextExtractorTest.php` exposed two pre-existing ToUnicode `usecmap` failures:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 625 assertions, 2 failures
```

Those failures are in CMap inheritance and are not caused by this page-label fallback change. They should be handled by a separate CMap-focused markerPDF slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page-tree selection, decoded stream boundary, page-label formatter, and page-resource review paths. GPU/OCR/model execution, Surya/Texify/Torch, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat the accepted page-resource inherited font/form lookup, escaped `/Kids`, generation-mismatched page `/Resources`, page Parent generation, trailer `/Root` generation, direct/indirect resource wrapper, ProcSet, or image XObject inheritance slices. The bounded behavior is only page-label fallback suppression when a catalog page tree exists but live page selection is empty because `/Kids` points at stale page generations.
