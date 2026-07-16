# Outline Metadata Page Label Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T091117Z`
Base accepted HEAD: `a45ca97f406d7ee0c5dd0511dc2a10ff6abec006`

## Source Truth

Upstream markerPDF exposes document TOC/navigation and page metadata through the PDFium/pdftext path. The native no-GPU PHP port keeps that boundary by resolving catalog outlines, destinations, and page labels as review metadata while visible WordPress text comes only from page content streams.

## Behavior

`PdfMetadataExtractor` now enriches catalog `document_outline` item rows with resolved catalog `PageLabels` and adds a compact `document_outline.page_labels` summary. The enrichment runs after destination resolution, so direct explicit destinations, named destinations, and GoTo action destinations all inherit the same target-page label behavior.

Labels, outline titles, URI operands, and action payloads remain review-only. They are not promoted into visible WordPress paragraphs.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPageLabelBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries page labels onto document outline metadata rows
Expected: ['Cover-iii','Chapter 7','Appendix-A']
Actual: []
FAIL keeps outline page labels and action operands out of visible WordPress text
Condition is not true
1 test files, 9 assertions, 2 failures
```

## Verification

Focused test after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPageLabelBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries page labels onto document outline metadata rows
PASS keeps outline page labels and action operands out of visible WordPress text
1 test files, 23 assertions, 0 failures
```

Adjacent outline/metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*Test.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php
22 test files, 906 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-page-label-boundary-currentbase.php
```

The smoke emits `document_outline_page_labels` and `navigation_page_labels` as `['Cover-iii','Chapter 7','Appendix-A']`, `visible_text_excludes_outline_labels=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat duplicate PageLabels `/Nums` key handling, PageLabels extraction/preview behavior, `PdfOutlineExtractor` navigation page labels, outline destination action page-label structure context, trailer Root/Info ownership, outline Prev/Last/missing-parent/generation/object-stream/xref boundaries, or named-destination generation filtering. It only maps already resolved page labels into document-level outline metadata rows.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page-label extractor, destination resolver, metadata extractor, and outline review/example path. GPU/OCR/PDFium/Python/model execution and external PDF tools remain intentionally out of scope.
