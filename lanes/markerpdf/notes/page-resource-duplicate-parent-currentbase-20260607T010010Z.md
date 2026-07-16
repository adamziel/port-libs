# markerpdf page resource duplicate Parent current base

Slice: `markerpdf-page-resource-inheritance-current-base-20260607T010010Z`

## Source truth

- Upstream markerPDF delegates searchable-PDF text extraction to parser layers before OCR/model execution. In the native no-GPU PHP port, page `/Resources` are an inherited page-tree attribute and must follow the effective page parent chain before WordPress paragraph rendering.
- Duplicate top-level dictionary keys in this lane are treated consistently with recent page-resource, AcroForm, outline, and PageLabels boundaries: the last top-level value is authoritative while nested private dictionaries remain review-only decoys.
- This slice stays inside native PDF parser behavior: page tree lineage, inherited resource dictionaries, Type0 ToUnicode font lookup, Form XObject expansion, and WordPress import smoke coverage only.

## Change

- `PdfTextExtractor` now resolves duplicate top-level page `/Parent` keys with the last value before inherited `/Resources` lookup.
- This aligns text extraction with `PdfPagePropertyExtractor` review metadata, which already selected the last duplicate `/Parent` value through its dictionary-entry helper.
- Added `PdfPageResourceDuplicateParentCurrentBaseTest.php` with a malformed page dictionary containing `/Parent 99 0 R /Parent 2 0 R`. The first parent is a detached decoy resource owner; the last parent is the catalog-selected `/Pages` node that supplies the current `/Font` and `/XObject` resources.
- Added `wordpress-pdf-page-resource-duplicate-parent-currentbase.php` to show the WordPress import path emits current inherited font/form paragraphs and excludes the detached parent branch without Python, OCR, models, or external PDF tools.

## Red-first evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateParentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last duplicate page Parent key before inherited resource lookup
Expected: Duplicate Parent current font/form text
Actual: A
1 test files, 1 assertions, 1 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateParentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last duplicate page Parent key before inherited resource lookup
1 test files, 18 assertions, 0 failures
```

Neighboring resource-lineage check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 348 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-parent-currentbase.php
last_parent_lineage_selected=true
current_resource_object_selected=true
current_font_and_form_selected=true
detached_parent_resource_excluded=true
payload_in_visible_text=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-overlap

This does not repeat accepted page-tree resource inheritance, nested private `/Parent` exclusion, duplicate catalog `/Kids` parent-lineage selection, parent wrapper references, escaped page-tree names, generation-mismatched `/Parent` or `/Kids`, null or malformed `/Resources`, exact resource generation selection, resource category wrappers, Form XObject null-resource inheritance, image XObject resource review, xref repair, metadata, annotations, forms, security preflight, table/equation handoff, or OCR/model behavior. The bounded behavior is only duplicate top-level page `/Parent` key selection before inherited page `/Resources` lookup.

## Dependency closure

No new support component is needed. This reuses the existing native PHP PDF tokenizer, page-tree walker, resource dictionary resolver, Type0 ToUnicode parser, and Form XObject expansion path. GPU/model OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for markerPDF under the current no-GPU directive.

## Next

Continue non-overlapping native searchable-PDF work around fonts, CMaps, content-stream operators, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, and supplied-boundary table/equation handoffs.
