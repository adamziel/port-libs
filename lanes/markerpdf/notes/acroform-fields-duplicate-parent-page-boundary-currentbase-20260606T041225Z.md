# markerpdf AcroForm duplicate Parent/P boundary current base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T041225Z`

## Source Truth

- Upstream markerPDF source remains the PDF-to-structured-content boundary from `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; upstream has no focused Python unit test for duplicate AcroForm Widget ownership keys, so this slice follows the lane's native PDF parser boundary and existing last-top-level `/Fields` and `/Kids` duplicate-key behavior.
- PDF Widget annotation dictionaries may carry `/Parent` field references and `/P` page back-references. This native no-GPU slice treats duplicate top-level `/Parent` and `/P` keys consistently with duplicate `/Fields` and `/Kids`: the last top-level dictionary value is authoritative for page-owned field repair.
- No OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, model worker, browser, live service, or external PDF tool was executed.

## Implementation

- `PdfAcroFormExtractor` now resolves Widget `/Parent` ownership and `/P` page ownership with the last top-level duplicate key before field-tree repair, page-widget repair, widget metadata rows, and calculation-order widget review.
- The change is scoped to AcroForm parent/page ownership paths. Generic dictionary value lookups remain unchanged.
- Added a focused fixture where stale first duplicate `/Parent` and `/P` keys would previously promote the wrong field or exclude the current page-owned widget.

## Red-First Evidence

Before the source fix, the new focused test failed because the first duplicate keys were trusted:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateParentPageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses last duplicate Widget Parent and P keys before page-owned AcroForm field repair
Expected: [duplicate.parent.current, duplicate.page.current]
Actual: [stale.parent.first, duplicate.parent.stale-last, duplicate.page.stale-last]
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateParentPageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses last duplicate Widget Parent and P keys before page-owned AcroForm field repair
1 test files, 54 assertions, 0 failures
```

Adjacent AcroForm field family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*Test.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 1542 assertions, 0 failures
```

Broader AcroForm/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php
Focused test run: 49 selected test files (root lock skipped)
49 test files, 3927 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-parent-page-currentbase.php
```

It emits `last_duplicate_parent_selected=true`, `stale_first_parent_excluded=true`, `stale_last_parent_excluded=true`, `last_duplicate_page_selected=true`, `stale_last_page_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateParentPageBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-parent-page-currentbase.php
```

Whitespace check passed:

```text
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, wrong-page `/P` rejection, direct widget `/Fields` normalization, parent-without-`/Kids` repair, explicit empty `/Kids` exclusion, child-root normalization, duplicate `/Fields`, duplicate `/Kids`, comment-split references, indirect `/Fields`/`Kids` arrays, token-aware array parsing, generation-exact references, indirect scalar/numeric/type operands, widget appearance/action/XFA/signature review, xref repair, stream filters, fonts, images, outlines, security preflight, or supplied layout/order slices. The bounded behavior is only duplicate top-level Widget `/Parent` and `/P` ownership-key selection before page-owned AcroForm field repair.

## Dependency Closure

No new support component is needed. This patch reuses the native PDF object scanner, generation-aware reference resolver, top-level dictionary scanner, page tree walker, page `/Annots` parser, widget map, field hierarchy builder, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of scope under the no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
