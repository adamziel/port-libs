# markerPDF annotations links escaped page-tree boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T040219Z`
Session: `port-dev-markerpdf-annotations-links-20260606T040219Z`
Base: `d8a1a2e5c053ca2e28ed33fa0bdda224a560bb72`

## Source truth

- markerPDF/PDF import promotes link and markup annotation metadata after searchable-PDF page extraction. The native no-GPU PHP lane owns the PDF object parser boundary before WordPress span promotion.
- PDF names may encode bytes with `#xx` escapes. Therefore `/T#79pe /#43atalog`, `/P#61ges`, `/K#69ds`, and `/T#79pe /P#61ge` are equivalent to `/Type /Catalog`, `/Pages`, `/Kids`, and `/Type /Page`.
- Page-tree leaves selected through the catalog are authoritative. Literal stale `/Type /Page` objects outside that tree must not become fallback annotation/link pages.

## Implementation

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now identify Catalog and Page objects through decoded PDF-name lookups instead of literal `/Type /Catalog` and `/Type /Page` regex checks.
- `PdfMarkupAnnotationExtractor` now resolves page-tree `/Pages` and `/Kids` through its decoded dictionary scanner, matching the existing annotation/link dictionary behavior.
- `PdfAnnotationLinkEscapedPageTreeBoundaryCurrentBaseTest.php` covers escaped catalog/page-tree names plus a literal stale page object outside the selected tree. The current page annotations, promoted link, and markup review win; the stale fallback page link is excluded.
- `wordpress-pdf-annotation-link-escaped-page-tree-currentbase.php` emits a WordPress paragraph smoke and review summary with all action/model/external-tool execution flags false.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkEscapedPageTreeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 32 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLink*CurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotation*.php lanes/markerpdf/tests/PdfPageAnnots*LinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
```

Result: `45 test files, 1816 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-escaped-page-tree-currentbase.php
```

Result: emitted `annotation_page_object=3`, `promoted_link_objects=[7]`, `markup_objects=[8]`, `literal_fallback_page_excluded=true`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationLinkEscapedPageTreeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-annotation-link-escaped-page-tree-currentbase.php
```

Result: all reported `No syntax errors detected`.

## Non-overlap

This does not repeat accepted escaped page `/Ann#6fts` lookup, escaped Link dictionary keys, page-generation membership, page-tree Kids token parsing, annotation `/P` page-reference ownership, primary action array/scalar rejection, comment dictionary boundaries, link state/presentation metadata, URI base, QuadPoints, crop/rotation/UserUnit, xref repair, AcroForm escaped page-tree repair, OCR/model execution, or external PDF tooling. The bounded behavior is decoded page-tree Catalog/Pages/Kids/Page names before annotation/link/markup page discovery, with stale literal fallback pages excluded.

## Dependency closure

No new support component is needed. The patch reuses the lane's native PDF-name decoder and token-aware dictionary readers. GPU/model OCR, Surya/Texify/Torch, Python markerPDF runtime execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

Root harness: not run - isolated micro-slice.
