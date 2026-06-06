# Page Resource Tree Wrapper Current Base

- Slice/session: `markerpdf-page-resource-inheritance-current-base-20260606T081444Z` / `port-dev-markerpdf-resource-inherit-20260606T081444Z`.
- Accepted base: `ebe3852fd7a4b86c1c6805bcbe033ba165d43ceb`.
- Scope: native no-GPU searchable-PDF parsing only. No OCR, Surya, Texify, Torch, PDFium, external PDF tools, or model workers were run.

## Behavior

PDF page-tree entries are ordinary indirect objects, so a catalog `/Pages` reference or a `/Kids` array entry can legally point at an indirect object whose value is another exact-generation indirect reference. The native parser already resolved wrapper references for page `/Resources`, but page-tree traversal still treated the wrapper body itself as the `/Pages` or `/Page` object. That dropped ordered page discovery and blocked inherited parent resources for wrapped page trees.

This patch adds exact-generation page-tree reference resolution in both:

- `PdfTextExtractor`, for ordered page traversal, `/Parent` membership checks, text extraction, Form XObject resource inheritance, outline page counts, and page labels.
- `PdfPagePropertyExtractor`, for page boundary/resource metadata over the same selected page path.

The resolver returns the final object number/generation/body so resource metadata is attributed to the actual `/Pages` owner, not to the wrapper object. Wrapper cycles and stale generations still fail closed.

## Red-First Evidence

Before source changes, the new focused test failed on the positive wrapper fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves catalog Pages and Kids wrapper references before inherited resource lookup
Expected: ['Wrapped page-tree inherited font text', 'Wrapped page-tree inherited form text']
Actual: []
PASS fails closed when page-tree wrapper references use stale generations
1 test files, 9 assertions, 1 failures
```

After source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves catalog Pages and Kids wrapper references before inherited resource lookup
PASS fails closed when page-tree wrapper references use stale generations
1 test files, 22 assertions, 0 failures
```

Adjacent page-resource current-base family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceKidsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
27 PASS cases
4 test files, 309 assertions, 0 failures
```

## WordPress Smoke

`lanes/markerpdf/examples/wordpress-pdf-page-resource-tree-wrapper-currentbase.php` emits Gutenberg paragraphs for inherited font text and inherited Form XObject text from a wrapped catalog `/Pages` object and a wrapped `/Kids` page entry. The smoke comment records `catalog_pages_wrapper_resolved=true`, `kids_wrapper_resolved=true`, `resource_owner_object=2`, `resource_object=10`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Final Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-tree-wrapper-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php` => 1 test files / 22 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceKidsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php` => 4 test files / 309 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-resource-tree-wrapper-currentbase.php` => emits the expected wrapper-resolution metadata and Gutenberg paragraphs.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`.
- `git diff --check -- lanes/markerpdf` => clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted generation-exact `/Kids` slice, catalog-parent boundary slice, nested `/Kids` token-boundary slice, or page `/Resources` wrapper slices. Those covered stale generation references, detached parent exclusion, nested decoys, and resource-value wrappers. This patch covers the page-tree node reference itself resolving through indirect wrapper objects before ordered page traversal and inherited resource lookup.

## Dependency Closure

No new support component is needed. The slice reuses the existing native object table, current-generation tracking, indirect-reference tokenizer, page-tree traversal, CMap/font-map parsing, and page resource metadata extractors. Remaining model/OCR gaps stay explicitly out of scope under the no-GPU markerPDF lane direction.

## Next

Continue with non-overlapping native PDF parser fidelity around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
