# markerpdf annotations-links relative path boundary current-base

Slice: `markerpdf-annotations-links-boundary-current-base-20260607T063815Z`
Base: `5b53644c4db20cfb702ed9ef7894f15ca40cdc21`

Implemented a native no-GPU PDF Link annotation URI boundary: path-relative URI references such as `guide.html#setup` and query-only references such as `?download=1` are valid review-safe `/URI` action targets even when the catalog has no `/URI /Base`. They are now promoted to WordPress spans as relative Markdown links while network-path references (`//host/path`) and backslash paths remain review-only and unpromoted.

Source-truth boundary: this follows the PDF Link annotation `/A << /S /URI /URI (...) >>` action model and URI-reference behavior used by PDF parser/viewer stacks; no OCR, Surya, Texify, Torch, pypdfium raster execution, Python model workers, or external PDF tools are invoked.

Verification:

- Red-first probe before source edit: `PdfLinkAnnotationExtractor::extractPageLinks()` returned `[]` for `/URI (guide.html#relative)` without a catalog base.
- Focused tests after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRelativePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriBaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationSchemeRelativeUriBoundaryCurrentBaseTest.php` => `3 test files, 94 assertions, 0 failures`.
- Wider annotation-link family after fix: `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfLinkAnnotation*Test.php' -o -name 'PdfAnnotationLink*Test.php' -o -name 'PdfPageAnnots*Link*Test.php' -o -name 'PdfPageAnnotationWidgetLinkCurrentBaseTest.php' -o -name 'PdfPageWidgetFieldActionLinkCurrentBaseTest.php' \) | sort)` => `53 test files, 1806 assertions, 0 failures`.
- WordPress smoke after fix: `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-relative-path-currentbase.php` exits 0 with `path_relative_without_base_promoted=true`, `query_relative_without_base_promoted=true`, `parent_relative_without_base_promoted=true`, `network_path_rejected=true`, `backslash_path_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Syntax/diff checks: `php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`, `php -l lanes/markerpdf/tests/PdfLinkAnnotationRelativePathBoundaryCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-relative-path-currentbase.php` all report no syntax errors; `git diff --check -- lanes/markerpdf` reports no whitespace errors.

Dependency closure: no new support component is needed. This reuses the native PHP PDF action reviewer, annotation/link extractor, and Markdown postprocessor.
