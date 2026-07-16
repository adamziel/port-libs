# markerPDF AcroForm Fields Trailer Root Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T023020Z`
Base accepted HEAD: `ca8ce650f8d6c127d28fd0204dd6d51033a95414`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text and document structure from parser-backed pdftext/PDFium boundaries before OCR/model work. For native PHP no-GPU import, the current PDF trailer `/Root` catalog must own the `/AcroForm` dictionary and page tree used to map page-owned Widget annotations into field review metadata.

No OCR, Surya, Texify, Torch, PDFium rendering, model workers, JavaScript/form actions, browser automation, or external PDF tools were executed.

## Behavior

`PdfAcroFormExtractor` now checks the final classic-xref `startxref` trailer chain for `/Root` before broad catalog scanning. When the current root catalog is present and generation-exact, it supplies both:

- the `/AcroForm /Fields` dictionary used for root field traversal; and
- the `/Pages` tree used to discover page-owned Widget annotations for omitted parent fields and standalone widget fields.

The focused fixture keeps a stale lower-numbered catalog with a stale AcroForm email field, while the final trailer points `/Root` to the current catalog. The extractor now returns only `current.email`, current page-owned `current.category`, and standalone page widget `current.inline`; stale field labels and values stay out of review metadata and visible WordPress text.

## Verification

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-trailer-root-currentbase.php
=> no syntax errors
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current trailer Root for AcroForm Fields and page widget boundaries

1 test files, 35 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 2489 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-trailer-root-currentbase.php
```

The smoke emits `field_names=["current.email","current.category","current.inline"]`, `need_appearances_from_current_root=true`, `current_page_widget_parent_promoted=true`, `current_standalone_widget_promoted=true`, `stale_catalog_field_excluded=true`, `visible_text_uses_current_root=true`, `form_values_visible_in_text=false`, and all Python/model/external-tool/action execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct Widget `/Fields` normalization, token-aware `/Fields` and `/Kids` arrays, generation-exact form refs, indirect widget operands, alternate `/TU` and `/TM` review, widget appearance state, submit/reset action review, XFA/signature review, trailer-root outline metadata, or xref-stream metadata slices. The bounded behavior is specifically current classic-xref trailer `/Root` ownership for AcroForm field and page-widget boundary extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation-valid reference checks, dictionary/array parser, classic xref trailer scanner, page tree walker, page annotation widget map, field hierarchy builder, action walker, and WordPress smoke path. Full upstream live OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
