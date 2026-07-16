# MarkerPDF Annotation Link Object-Stream Review Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260607T011719Z`
Base: `4841a8141eb09153691392303a67ae59443e4510`
Date: 2026-06-07 UTC

## Source Truth

PDF xref-stream type-2 entries select compressed object bodies from `/ObjStm`
containers. markerPDF/pdftext-style link handling must therefore promote and
review the xref-selected annotation body, not a stale same-object direct body
left elsewhere in the file.

## Patch

`PdfAnnotationExtractor` now applies the same bounded xref-stream object
selection used by `PdfLinkAnnotationExtractor` for annotation review rows:

- latest `startxref` xref streams decode Flate/ASCIIHex stream bytes;
- direct type-1 rows select exact direct objects by offset/generation;
- type-2 rows select object-stream members by explicit member index;
- selected generation-zero rows override stale direct generation maps;
- objects mentioned by an xref stream but not selected remain suppressed.

This keeps page annotation review metadata aligned with WordPress link
promotion for compressed Link annotations.

## Red-First Evidence

Before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php`

failed with stale direct geometry:

`Expected: [72,700,222,718]`
`Actual: [238,700,380,718]`

## Verification

Focused:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php`

Result: `1 test files, 26 assertions, 0 failures`

Neighboring annotation/link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkIndirectArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php`

Result: `9 test files, 215 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-annotation-link-objectstream-review-currentbase.php`

Result: emits `stale_direct_annotation_excluded=true`,
`annotation_payload_text_excluded_from_visible_text=true`,
`executes_python_or_models=false`, `executes_external_pdf_tools=false`, and
`executes_pdf_actions=false`.

Syntax:

`php -l lanes/markerpdf/src/PdfAnnotationExtractor.php`
`php -l lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php`
`php -l lanes/markerpdf/examples/wordpress-pdf-annotation-link-objectstream-review-currentbase.php`

Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF parsing,
zlib stream decoding, existing xref free-entry suppression, and existing
annotation/action/link review classes. No Python, CUDA, OCR, model execution,
PDF action execution, external PDF tools, or live services were used.

## Non-Overlap

This does not repeat previous link-only object-stream promotion,
object-stream action selection, free annotation suppression through `/Prev`,
duplicate page `/Annots`, duplicate annotation dictionary key, or indirect
annotation-array fragments. It closes the separate current-base gap where
`PdfAnnotationExtractor` review rows used stale direct annotation bodies while
`PdfLinkAnnotationExtractor` already promoted the xref-selected compressed
body.
