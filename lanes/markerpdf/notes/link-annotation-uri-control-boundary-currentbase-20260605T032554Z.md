# markerPDF link annotation URI control boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T032554Z`

Base accepted HEAD: `a7753d4a1109c6b35dd6198d9615fa05af4e8895`

## Source truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through pdftext/PDFium before Markdown post-processing. In this native no-GPU PHP lane, page `/Annots` link actions are review metadata and only safe URI actions may become WordPress Markdown links. URI strings containing decoded ASCII control/space bytes are invalid for direct WordPress href promotion and remain review-only.

## Behavior

`PdfActionReviewExtractor::isSafeUri()` and the mirror policy helper in `PdfLinkAnnotationExtractor` now reject decoded URI strings containing `0x00` through `0x20` or `0x7F` anywhere in the action URI.

The focused fixture covers:

- a clean `https://example.com/clean-docs` Link annotation that still promotes to a Markdown link;
- a decoded newline after an `https:` URI prefix;
- a decoded tab after a `mailto:` URI prefix;
- a decoded carriage return inside a root-relative URI.

The control-byte links remain present in annotation action review rows as `blocked-unsafe-uri` with `is_safe_uri=false`, but `PdfLinkAnnotationExtractor` does not promote them onto supplied Marker/pdftext spans.

## Red-First Evidence

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php`

Result: failed after 3 assertions because the three control-byte URI actions were classified as `review-uri`.

## Verification

After the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php`

Result: `1 test files, 27 assertions, 0 failures`.

Focused link/action regression group:

`php tools/run-tests.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php`

Result: `10 test files, 650 assertions, 0 failures`.

The WordPress smoke is `lanes/markerpdf/examples/wordpress-pdf-link-annotation-uri-control-boundary-currentbase.php`. It emits `control_newline_blocked=true`, `control_tab_blocked=true`, `control_relative_blocked=true`, `unsafe_control_uri_promoted=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page `/Annots` top-level selection, escaped `/Annots` names, tokenized annotation arrays, link annotation generation exactness, crop/rotation/UserUnit geometry, QuadPoints span matching, link presentation metadata, remote GoToR review, hidden/no-view annotation exclusion, or JavaScript action-chain review. The new boundary is specifically decoded control/space bytes inside URI action strings before WordPress Markdown href promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF action parser, link annotation extractor, supplied Marker/pdftext span promotion, Markdown post-processor, and WordPress smoke path. Live OCR/model execution, pypdfium/PDFium runtime rendering, Surya/Torch/Texify, and exact upstream GPU/model benchmark parity remain intentionally out of scope for this no-GPU native parser slice.
