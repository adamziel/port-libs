# Annotation/link page-generation boundary current-base slice

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T110929Z`

Accepted base: `27b92494afc5fa54fb8216644a9669563bb2637e`

## Behavior

PDF page-tree `/Kids` entries name exact indirect references. This slice carries the
referenced page object generation into native annotation extraction, link promotion,
and text-markup review so a current page `3 1 R` does not accept annotations whose
own `/P` points at stale `3 0 R`, even when those stale annotations are present in
the current page's `/Annots` array.

The patch updates:

- `PdfAnnotationExtractor`
- `PdfLinkAnnotationExtractor`
- `PdfMarkupAnnotationExtractor`

The exact generation is also used to load the page body for annotation/link geometry,
so stale same-object-number page dictionaries do not donate `/Annots`, `/MediaBox`,
or other page-local annotation boundaries.

## Evidence

Red baseline before source changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 1 failures`; the extractor selected stale
annotation objects `[8, 10]` instead of current page-generation objects `[7, 9]`.

Focused test after source changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php`

Result: `1 test files, 23 assertions, 0 failures`.

Focused annotation/link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`

Result: `8 test files, 634 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-annotation-link-page-generation-boundary-currentbase.php`

The smoke emits `stale_page_generation_excluded=true`,
`stale_span_promoted=false`, `executes_pdf_actions=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted link annotation object-generation, page-reference,
parent-generation geometry, primary-action, URI-control, remote-GoToR, QuadPoints,
or name-tree limits work. The new boundary is exact page-tree `/Kids` generation
handoff into annotation `/P` membership and page body selection.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF object
dictionary/reference parser and keeps the no-GPU markerPDF scope: no Python models,
OCR, pypdfium rendering, JavaScript execution, or external PDF tools are invoked.
