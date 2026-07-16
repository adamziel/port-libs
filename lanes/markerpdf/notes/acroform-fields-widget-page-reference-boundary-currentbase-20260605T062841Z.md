# markerpdf AcroForm widget page-reference boundary current base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T062841Z`

## Source Truth

- Upstream markerPDF source remains the PDF-to-structured-content boundary from `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; upstream has no focused Python test for malformed AcroForm widget page ownership, so this slice follows the lane's native PDF parser boundary.
- PDF annotation dictionaries may carry `/P`, the page object that contains the annotation. This native no-GPU slice treats page `/Annots` as the ownership signal only when `/P` is absent or resolves to the same page; an explicit `/P` pointing at a different page is malformed and fails closed for field repair.
- No OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, model worker, browser, live service, or external PDF tool was executed.

## Implementation

- `PdfAcroFormExtractor::pageWidgetMap()` now rejects Widget annotations whose explicit `/P` reference does not resolve to the page object that listed the widget in `/Annots`.
- Widgets without `/P` continue to be accepted from the page `/Annots` list, preserving existing malformed-field repair for PDF producers that omit the optional back-reference.
- Matching `/P` references on later pages still preserve page index/object metadata and can repair omitted parent fields.

## Red-First Evidence

Before the source fix, the new focused test failed because wrong-page widgets were promoted:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects wrong-page AcroForm widget P references before page-owned field repair
Expected: [listed.first, floating.nop, listed.second]
Actual: [listed.first, wrongpage.parent, wrongpage.inline, floating.nop, listed.second]
1 test files, 424 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 457 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php
Focused test run: 26 selected test files (root lock skipped)
26 test files, 2734 assertions, 0 failures
```

The updated WordPress smoke exits `0`:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

It emits `wrong_page_widget_p_references_excluded=true`, `matching_widget_p_second_page_preserved=true`, `detached_widget_excluded=true`, `array_decoy_fields_excluded=true`, `comment_widget_subtype_decoys_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Whitespace check passed:

```text
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct widget `/Fields` normalization, child-root normalization, indirect `/Fields`/`Kids` arrays, token-aware array parsing, generation-exact references, indirect scalar/numeric/type operands, widget appearance/action/XFA/signature review, link annotation widget promotion, xref repair, stream filters, fonts, images, outlines, security preflight, or supplied layout/order slices. The bounded behavior is only explicit Widget `/P` page ownership validation before page-owned AcroForm field repair.

## Dependency Closure

No new support component is needed. This patch reuses the native PDF object scanner, generation-aware reference resolver, page tree walker, page `/Annots` parser, widget map, field hierarchy builder, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of scope under the no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
