# markerPDF annotations links duplicate URI Base boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T191031Z`

Base accepted HEAD: `40e4afa74effef117e3761e0e7b8018882962824`

## Source-truth boundary

- Upstream `sddai/markerPDF` is pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path delegates PDF parsing/link extraction to PDF parser/PDFium/pdftext behavior before model/OCR stages.
- Under the current no-GPU markerPDF scope, catalog URI base handling is native PHP parser/review behavior. Duplicate catalog `/URI /Base` keys are ambiguous and must fail closed before WordPress import rewrites relative Link annotation hrefs.
- This slice keeps safe relative and fragment hrefs as relative, continues to promote safe absolute links, and keeps unsafe JavaScript actions review-only.

## Change

- `PdfActionReviewExtractor::catalogUriBase()` now rejects duplicate `/Base` keys in the catalog URI dictionary before resolving relative link annotation URIs.
- Added a focused duplicate-base PDF fixture test proving `articles/import.html#setup` and `#field-reference` remain unresolved when the catalog supplies two `/Base` keys.
- Added a WordPress smoke that emits linked spans for the relative, fragment, and absolute hrefs while confirming duplicate bases and unsafe JavaScript are not promoted.

## Red/green evidence

Pre-fix focused run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseDuplicateBoundaryCurrentBaseTest.php
```

Result: `1 test files, 4 assertions, 1 failures`; expected `articles/import.html#setup`, actual `https://evil.example.com/rewrite/articles/import.html#setup`.

After fix:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseDuplicateBoundaryCurrentBaseTest.php
```

Result: `1 test files, 44 assertions, 0 failures`.

Adjacent URI-base regression run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationSchemeRelativeUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRelativePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php
```

Result: `7 test files, 246 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-link-catalog-uri-base-duplicate-boundary-currentbase.php
```

Result: exits 0 and reports `duplicate_bases_promoted=false`, `unsafe_uri_promoted=false`, `relative_resolved_from_base=false`, `fragment_resolved_from_base=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```sh
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseDuplicateBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-catalog-uri-base-duplicate-boundary-currentbase.php
```

Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted ordinary catalog URI Base resolution, malformed Base operand rejection, scheme-relative URI blocking, relative-path promotion without a base, control-byte URI blocking, action duplicate subtype handling, previous URI review, remote GoToR review, annotation Rect/QuadPoints/CropBox geometry, xref free annotation/action suppression, AcroForm field actions, metadata, images, forms, stream filters, CMaps, OCR, or model execution. The bounded behavior is only duplicate `/Base` keys in the catalog URI dictionary before Link annotation relative href rewriting.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, dictionary duplicate-key review metadata, catalog URI Base review, annotation/link action review, Markdown span merging, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch model execution, pypdfium/PIL raster rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
