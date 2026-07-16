# markerpdf annotations-links reference-chain current-base

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T153104Z`
Base: `866ea52a67a61e534ee4668a27bf164b07d3651b`

## Behavior

Mapped a native PDF annotation boundary where a page `/Annots` value resolves through one or more indirect references before reaching the annotation array. `PdfAnnotationExtractor` and `PdfLinkAnnotationExtractor` already followed this shape; `PdfMarkupAnnotationExtractor` now uses the same bounded reference-chain behavior, so URI Link promotion and Highlight review metadata stay aligned for WordPress imports.

The chain walker is depth-limited and tracks object/generation pairs, so cyclic annotation references remain bounded and do not produce review rows or visible Gutenberg text.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkMarkupReferenceChainBoundaryCurrentBaseTest.php`
  - `1 test files, 24 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkMarkupReferenceChainBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationFreedObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkIndirectArrayBoundaryCurrentBaseTest.php`
  - `5 test files, 208 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-annotation-link-markup-reference-chain-currentbase.php`
  - exits `0`; summary reports `annotation_objects=[7,8]`, `promoted_link_objects=[7]`, `markup_objects=[8]`, `reference_cycle_payload_promoted=false`, `annotation_payload_text_visible=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted indirect-array link slice, escaped annotation/page-tree keys, duplicate action keys, `/P` page ownership, action operand tail guards, URI base resolution, CCITT stream-filter boundaries, OCR/model handoffs, or image/raster execution. The new behavior is specifically markup extraction parity for chained page `/Annots` references.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF dictionary, array, object-reference, page-boundary, link promotion, markup review, and Markdown post-processing code already present in `lanes/markerpdf/src`; no Python, CUDA, OCR/model runtime, pypdfium, raster renderer, or external PDF tool is invoked.
