# AcroForm Fields Page-Tree Indirect Kids Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T010133Z`

## Scope

Upstream markerPDF routes searchable PDF parsing through PDF page/object traversal before model/OCR fallback. In the native no-GPU PHP boundary, catalog page-tree leaves are authoritative for page-owned Widget annotation repair. A `/Pages` node may store `/Kids` as a generation-checked indirect array object; detached page-like objects outside that catalog page tree must not become fallback sources for AcroForm field review.

## Change

- `PdfAcroFormExtractor::pageObjectNumbersFromTree()` now resolves `/Kids` with the existing bounded array-or-reference helper instead of accepting only direct arrays.
- The new focused fixture has `/Pages /Kids 20 0 R` where object `20` is `[4 0 R]`, plus a detached stale page object `3` with a stale Widget annotation. The current page field is retained with page index `0`; the detached stale field is excluded from AcroForm review and visible WordPress text.
- The WordPress smoke emits explicit flags for indirect page-tree resolution, stale page-widget exclusion, and no Python/model/external PDF tool execution.

## Red-First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses indirect page tree Kids arrays before AcroForm page widget repair (lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'current.indirectpage',
)
Actual: array (
  0 => 'current.indirectpage',
  1 => 'stale.detachedpage',
)

1 test files, 1 assertions, 1 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses indirect page tree Kids arrays before AcroForm page widget repair

1 test files, 28 assertions, 0 failures
```

## Non-Overlap

This does not repeat accepted AcroForm direct field dictionaries, indirect `/Fields` or field `/Kids` arrays, direct page Widget dictionaries, direct Widget `/Fields` normalization, page Widget parent-without-`/Kids` repair, child branch/root repair, parent ownership rejection, duplicate `/Fields` or `/Kids` keys, generation-exact references, object-stream field recovery, token/comment array parsing, wrong-page `/P` rejection, XFA/signature/action review, submit/reset review, stream filters, xref repair, annotations outside Widget repair, or OCR/model behavior. The bounded behavior is only indirect catalog page-tree `/Kids` arrays before page-owned AcroForm Widget repair.

## Verification

Final commands:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-page-tree-indirect-kids-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-page-tree-indirect-kids-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php
1 test files, 28 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php
4 test files, 638 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort) lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
47 test files, 3890 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-page-tree-indirect-kids-currentbase.php
Emits indirect_page_tree_kids_resolved=true, detached_stale_page_widget_excluded=true, form_values_visible_in_text=false, stale_page_text_visible=false, executes_python_or_models=false, and executes_external_pdf_tools=false.

git diff --check -- lanes/markerpdf
No whitespace errors.
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-aware indirect-reference validation, array/dictionary token parser, AcroForm field-tree walker, page Widget annotation map, text extraction boundary, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, form action execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
