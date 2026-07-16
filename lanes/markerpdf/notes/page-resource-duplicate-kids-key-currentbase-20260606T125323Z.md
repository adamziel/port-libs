# markerPDF page resource duplicate Kids key current base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T125323Z`
Session: `port-dev-markerpdf-resource-inherit-20260606T125323Z`
Base accepted HEAD: `a0ac4f921e09c2e2ea2cae8d976f06dea26b2753`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to parser layers before OCR/model execution. In the native no-GPU PHP port, the PDF page tree selects page leaves and inherited `/Resources` before WordPress paragraph rendering. Duplicate top-level PDF dictionary keys should follow the current dictionary boundary already used for page `/Resources` and AcroForm `/Kids`: a later top-level `/Kids` entry on a `/Pages` node supersedes an earlier stale child branch before page discovery, parent-child validation, and inherited resource lookup.

## Change

- `PdfTextExtractor::pageTreeKidObjectReferences()` now reads the last top-level `/Kids` value instead of the first.
- `PdfTextExtractor::pageTreeParentListsChild()` validates parent ownership against that same current `/Kids` value.
- Added a focused fixture where a root `/Pages` dictionary declares stale first `/Kids [10 0 R]` and current second `/Kids [20 0 R]`; inherited resources must come from branch object `20` and resource object `40`, not stale branch object `10` / resource object `30`.
- Added a WordPress smoke that emits the current two Gutenberg paragraphs and review flags proving stale first-branch font/Form XObject text is excluded.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last top-level page-tree Kids key before inherited resource lookup (lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current duplicate Kids inherited text',
  1 => 'Current duplicate Kids form text',
)
Actual: array (
  0 => 'Stale duplicate Kids resource leak',
  1 => 'Stale duplicate Kids form leak',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last top-level page-tree Kids key before inherited resource lookup

1 test files, 16 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageResource.*CurrentBaseTest\.php$|PdfPagePropertyExtractorTest\.php$' | sort)
Focused test run: 26 selected test files (root lock skipped)
...
26 test files, 926 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceKidsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 789 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceDuplicateKidsKeyCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-kids-key-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-kids-key-currentbase.php
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-kids-key-currentbase.php
<!-- markerpdf-page-resource-duplicate-kids-key-currentbase {"source":"native-pdf-page-resource-duplicate-kids-key-currentbase","native_boundary":"duplicate top-level page-tree /Kids keys use the current branch before inherited resource lookup","last_kids_branch_selected":true,"current_resource_object_selected":true,"current_text_imported":true,"stale_first_kids_branch_excluded":true,"stale_first_form_excluded":true,"executes_python_or_models":false,"executes_external_pdf_tools":false} -->
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted page-tree resource inheritance, duplicate catalog `/Kids` entries that reach the same page object through explicit `/Parent` lineage, escaped `/Kids`, indirect `/Kids` arrays, generation-exact `/Kids` and `/Parent` references, parent `/Kids` mismatch fail-closed behavior, page `/Resources` null/malformed/direct-tail/object-tail handling, resource category wrappers, stream-valued category rejection, Form XObject null/malformed resources, image XObject inheritance review, page `/Contents` non-inheritance, xref repair, metadata, annotations, forms, table/equation handoffs, or OCR/model paths. The bounded behavior is only duplicate top-level `/Kids` keys on a `/Pages` dictionary before inherited resource lookup.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/value parser, page-tree walker, parent-child validator, page-resource resolver, text extractor, page boundary metadata extractor, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
