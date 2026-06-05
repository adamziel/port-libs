# markerPDF page resource catalog-parent boundary

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T165651Z`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/page layers before OCR and model stages. In this native no-GPU boundary, the catalog `/Pages` `/Kids` traversal owns the selected page tree path. A page leaf whose `/Parent` points at a detached or stale `/Pages` node must not inherit fonts, Properties, or Form XObjects from that detached branch before WordPress paragraph rendering.

## Change

- `PdfTextExtractor` now trims page-resource lineage to the common prefix with the catalog-selected `/Kids` path when a page `/Parent` chain diverges, and marks the divergence as a fallback block.
- `PdfPagePropertyExtractor` now uses the same catalog-bounded common-prefix lineage for page resource review metadata.
- A detached `/Pages` node that lists the page can no longer inject inherited `/Resources` for visible text or page-boundary resource metadata.
- Matching `/Parent` chains on the selected catalog path still inherit ancestor resource dictionaries.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL excludes detached page Parent resources that are not on the catalog Kids path (lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'A',
)
Actual: array (
  0 => 'Detached parent font leak',
  1 => 'Detached parent form leak',
)
PASS keeps inherited resources when the page Parent matches the selected catalog path

1 test files, 14 assertions, 1 failures
```

## Focused verification

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes detached page Parent resources that are not on the catalog Kids path
PASS keeps inherited resources when the page Parent matches the selected catalog path

1 test files, 23 assertions, 0 failures
```

Adjacent page-resource/text/property run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1370 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-catalog-parent-boundary-currentbase.php
```

The smoke emits `catalog_page_count_preserved=true`, `detached_parent_resources_excluded=true`, `detached_form_xobject_excluded=true`, `page_resource_review_empty=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-catalog-parent-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-catalog-parent-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok
git diff --check -- lanes/markerpdf
0 issues
```

## Non-overlap

This does not repeat accepted page-tree resource inheritance, generation-exact `/Kids`, generation-mismatched `/Parent`, `/Parent` not listing child, top-level `/Parent` token parsing, null/malformed `/Resources`, resource-entry generation filtering, trailer `/Root` generation blocking, Form XObject null-resource inheritance, page `/Contents` non-inheritance, or image/annotation/metadata review slices. The bounded behavior is specifically a valid-looking but detached page `/Parent` branch that is not on the catalog-selected `/Kids` path.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware page-tree walker, resource dictionary resolver, Form XObject expansion, CMap/font text extraction, page-resource review metadata, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
