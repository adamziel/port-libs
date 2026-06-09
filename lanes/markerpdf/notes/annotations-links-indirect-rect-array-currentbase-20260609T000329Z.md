## Slice

`markerpdf-annotations-links-boundary-current-base-20260609T000329Z` on accepted base `48a59c8d15f1cb4b103c2c2657a62cb105c4a87a`.

## Behavior

PDF Link annotation `/Rect` may be a clean indirect object whose value is a four-number array. `PdfLinkAnnotationExtractor` already resolved that shape for span promotion, but `PdfAnnotationExtractor` left the review row rectangle as `null`. The patch makes annotation review use the existing native array resolver after direct and resolved trailing-operand checks, so clean indirect `/Rect` arrays are reviewable while tailed indirect arrays remain fail-closed.

No PDF actions execute. The WordPress path promotes only the clean URI link and keeps tailed `/Rect` decoy URI/JavaScript payloads out of span metadata and visible paragraphs.

## Verification

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectRectArrayBoundaryCurrentBaseTest.php`

Result before source edit: `1 test files, 3 assertions, 1 failures`; failure: valid indirect `/Rect` review row was `NULL`.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectRectArrayBoundaryCurrentBaseTest.php`

Result: `1 test files, 21 assertions, 0 failures`.

Adjacent annotation/link geometry group:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectRectArrayBoundaryCurrentBaseTest.php`

Result: `3 test files, 98 assertions, 0 failures`.

Broader annotation/link focused regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectRectArrayBoundaryCurrentBaseTest.php`

Result: `5 test files, 486 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-rect-array-currentbase.php`

Result: exits `0` and emits `indirect_rect_reviewed=true`, `indirect_rect_promoted=true`, `tailed_rect_promoted=false`, `tailed_action_review_leaked=false`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP object scanner, indirect object resolver, annotation review extractor, link annotation extractor, and WordPress span promotion path. GPU/model/OCR execution remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted indirect numeric coordinate elements, malformed `/Rect` operands, `/P` page-reference ownership, `QuadPoints`, URI base, action `/Next`, FileSpec, xref free annotation, AcroForm, metadata, image, font, OCR, or table slices. The bounded behavior is whole-object indirect `/Rect` array resolution for Link annotation review before WordPress link promotion.
