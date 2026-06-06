## markerpdf annotation link scheme-relative URI boundary current-base

Slice: `markerpdf-annotations-links-boundary-current-base-20260606T134853Z`

Accepted base: `380f73bd6771c85383ad351d5e11064bf53f0c34`

Behavior:

- PDF Link annotation `/A << /S /URI /URI (...) >>` actions whose URI begins with `//` are now classified as `blocked-unsafe-uri`.
- Those network-path references remain review-only annotation metadata and do not become WordPress Markdown links or supplied pdftext span `link_uri` values.
- Safe absolute `https://...` links and ordinary relative links resolved through catalog `/URI /Base` remain promoted.
- Annotation `/Contents` and URI payload text stay out of visible PDF text extraction.

Source-truth boundary:

- Upstream markerPDF delegates link annotation extraction through PDFium/pdftext-style page annotations; the native PHP lane must fail closed before WordPress link promotion when a URI target is not an explicitly allowed absolute scheme or an ordinary path-relative target.
- This patch reuses the existing native PHP PDF action review and link annotation promotion components. No Python, OCR, model execution, pypdfium, browser, or external PDF tools are involved.

Red-first evidence:

- Before the source fix, `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationSchemeRelativeUriBoundaryCurrentBaseTest.php` failed after 3 assertions because `//evil.example/protocol-relative.pdf` was reported as `review-uri`.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationSchemeRelativeUriBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 29 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCatalogUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php`
  - Result: `3 test files, 101 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php lanes/markerpdf/tests/PdfAnnotationLink*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnots*Link*CurrentBaseTest.php`
  - Result: `46 test files, 1500 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-scheme-relative-uri-currentbase.php > /tmp/markerpdf-link-scheme-relative.html`
  - Result: summary reports `scheme_relative_uri_blocked=true`, `scheme_relative_uri_promoted=false`, `safe_absolute_promoted=true`, `relative_uri_resolved_from_base=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

- No new support component is needed. The patch reuses `PdfActionReviewExtractor`, `PdfLinkAnnotationExtractor`, `MarkdownPostProcessor`, and `PdfTextExtractor`.
- Remaining model/OCR gaps stay out of scope under the current no-GPU markerPDF directive.

Non-overlap:

- Avoids the accepted PageLabels integer-overflow slice and the existing link/control-byte, catalog URI base, hidden flag, generation, widget, and destination boundary slices.
- This is specifically the `//` network-path URI promotion boundary.
