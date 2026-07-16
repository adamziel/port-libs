# markerPDF link annotation indirect numeric boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T061734Z`

Base accepted HEAD: `c6ac5df0374dd36163d5c0e76bc3d26f21646bd2`

## Source truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses pdftext/PDFium page annotation geometry before Markdown post-processing and WordPress-style import promotion. In this native no-GPU PHP lane, Link annotations remain review metadata unless their primary action is safe for WordPress span promotion. PDF numeric array operands may be indirect objects, and PDF references are generation-qualified.

## Behavior

`PdfAnnotationExtractor` and `PdfLinkAnnotationExtractor` now parse numeric arrays token-by-token instead of using a broad numeric regex when object context is available. Exact-generation indirect numeric operands are resolved inside Link annotation `/Rect`, `/QuadPoints`, border color arrays, border arrays, and inherited page box arrays. Wrong-generation or unresolved numeric references are skipped rather than being treated as raw object and generation numbers.

The focused fixture covers:

- Link `/Rect [20 0 R 21 0 R 22 0 R 23 0 R]` promoted to a safe WordPress Markdown link;
- Link `/QuadPoints` made entirely of exact-generation indirect numeric operands promoted only for the quad span;
- inherited `/CropBox` numeric operands stored as indirect objects before visible-page clipping;
- indirect RGB border color operands in link review metadata;
- wrong-generation `/Rect [60 1 R 61 1 R 62 1 R 63 1 R]` where only generation-zero numeric helpers exist, proving no stale link promotion occurs.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php`

Result: `1 test files, 31 assertions, 0 failures`.

Focused link/annotation regression group:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php`

Result: `7 test files, 550 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-numeric-boundary-currentbase.php`

Result: exits 0 and emits `indirect_rect_promoted=true`, `indirect_quad_promoted=true`, `wrong_generation_promoted=false`, `annotation_payload_text_visible=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Non-Overlap

This does not repeat accepted top-level `/Annots` selection, escaped annotation dictionary keys, annotation array token boundaries, exact annotation object generation selection, crop/rotation/UserUnit mapping, QuadPoints span matching with direct numbers, previous URI review metadata, URI control-byte blocking, remote GoToR review, or link presentation review. The new boundary is per-element indirect numeric operands inside annotation and page geometry arrays before WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object table, exact-generation object body lookup, annotation extractors, link span promotion, Markdown post-processor, and WordPress smoke path. Live OCR/model execution, pypdfium/PDFium runtime rendering, Surya/Torch/Texify, and exact upstream GPU/model benchmark parity remain intentionally out of scope for this no-GPU native parser slice.
