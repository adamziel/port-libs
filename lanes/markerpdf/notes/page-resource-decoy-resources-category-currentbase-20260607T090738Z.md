# markerPDF page resource decoy Resources category current-base

## Scope

Upstream markerPDF relies on PDF parser resource dictionaries before OCR/model stages. In the native no-GPU PHP path, a page-tree `/Resources` entry resolves to one effective resource dictionary. That dictionary may contain only PDF resource categories such as `/Font`, `/XObject`, `/ProcSet`, `/ColorSpace`, `/ExtGState`, `/Pattern`, `/Shading`, and `/Properties`; an invalid top-level `/Resources` key inside the resolved resource dictionary is a decoy and must not be exposed as a page-resource review category.

This slice keeps visible text extraction unchanged while tightening page-boundary metadata so WordPress imports do not treat decoy `/Resources` keys as real import-review categories.

## Implementation

- `PdfPagePropertyExtractor` now normalizes `resources.categories` through an explicit PDF resource-category allowlist.
- The new focused fixture inherits `/Resources 10 0 R` from the page tree. Object `10` contains valid `/Font` and `/XObject` categories plus a decoy top-level `/Resources` dictionary with stale font/form references.
- Visible text still comes only from the valid font and current Form XObject; the decoy form text and resource name stay excluded.
- Page review metadata now reports `['Font', 'XObject']` instead of `['Font', 'XObject', 'Resources']`.

## Red-First Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDecoyResourcesCategoryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores decoy Resources keys inside inherited resource dictionaries before page review (lanes/markerpdf/tests/PdfPageResourceDecoyResourcesCategoryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Font',
  1 => 'XObject',
)
Actual: array (
  0 => 'Font',
  1 => 'XObject',
  2 => 'Resources',
)

1 test files, 9 assertions, 1 failures
```

After the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDecoyResourcesCategoryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores decoy Resources keys inside inherited resource dictionaries before page review

1 test files, 14 assertions, 0 failures
```

Adjacent page-resource/property suite:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDecoyResourcesCategoryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 33 selected test files (root lock skipped)
...
33 test files, 1059 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-decoy-resources-category-currentbase.php
```

The smoke exits `0` and emits `decoy_resources_category_excluded=true`, `current_xobject_selected=true`, `stale_form_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, duplicate resource category selection, direct resource dictionary trailing-token rejection, malformed page `/Resources` fail-closed behavior, generation-exact `/Parent` and `/Kids` traversal, ProcSet review, Form XObject null-resource inheritance, category stream rejection, page `/Contents` non-inheritance, image XObject review, xref repair, metadata, annotation, form, table, OCR, or model handoffs. The bounded behavior is only page-boundary review category normalization for invalid top-level `/Resources` decoys inside already-resolved inherited resource dictionaries.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP PDF object parser, page-tree resource resolver, page-boundary reviewer, text extractor, and WordPress smoke renderer. GPU/OCR/model execution, Surya/Texify/Torch, pypdfium/PDFium rendering, raster image conversion, decryption, PDF action execution, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU micro-slice.
