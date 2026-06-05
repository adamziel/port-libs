# markerPDF PageLabels trailer Root boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T030139Z`

## Source Truth

- Upstream markerPDF gets searchable PDF text and page metadata from the loaded PDF document, whose catalog is selected through the current trailer `/Root`, not by scanning for the first catalog-shaped object.
- PDF `/PageLabels` lives on the document catalog and supplies page-break/preview metadata for WordPress import. Stale catalog-shaped objects in older body sections must not relabel current pages.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview` now selects a generation-exact catalog from the latest classic trailer `/Root` before falling back to the previous catalog scan path used by simple fixture PDFs.
- The trailer scan is bounded before the latest `startxref` token so post-EOF root decoys cannot redirect preview labels.
- The selected root catalog drives both page collection and `page_label` metadata, keeping preview summaries aligned with `PdfTextExtractor::extractPageLabels()`.
- Added a focused fixture with a stale low-number catalog, stale `/PageLabels`, a current xref/trailer root pointing at the real catalog, and a post-`startxref` root decoy.
- Added a WordPress smoke that emits page-break metadata for `Current-4` and `Appendix-Z` while proving stale catalog labels and stale page objects stay excluded.

## Evidence

Red-first probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["Current-4","Appendix-Z"]
MarkerAppPreview::pageLabels(...) => ["stale-root-99"]
MarkerAppPreview::openPdfSummary(...) page object IDs => [3]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 85 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-trailer-root-currentbase.php
```

The smoke emits `page_labels=["Current-4","Appendix-Z"]`, `preview_page_labels=["Current-4","Appendix-Z"]`, `preview_page_object_ids=[9,11]`, `selected_preview_page_label="Appendix-Z"`, `trailer_root_catalog_selected=true`, `stale_catalog_rejected=true`, `post_startxref_root_decoy_rejected=true`, and execution flags false.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, `/Limits`, indirect operands, escaped names, PDFDocEncoding prefixes, generation-exact PageLabels dictionaries, top-level `/Kids` and `/Nums` token parsing, or page transition/outline label propagation. The bounded behavior is marker-app preview choosing the current trailer `/Root` catalog before stale catalog-shaped objects can affect page-label metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, balanced dictionary/value parser, generation-indexed object bodies, PageLabels formatter, preview summary, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
