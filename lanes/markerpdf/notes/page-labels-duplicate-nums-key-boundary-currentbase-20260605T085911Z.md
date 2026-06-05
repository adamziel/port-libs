# markerPDF PageLabels duplicate Nums key boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T085911Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through pdftext/PDFium before model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose leaf `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This malformed-boundary slice keeps the first valid duplicate `/Nums` page-index entry and rejects later duplicate relabeling so stale entries cannot replace WordPress page-break or preview metadata.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now preserves first-valid duplicate PageLabels page-index sections when collecting direct `/Nums` arrays and when merging child number-tree nodes.
- `MarkerAppPreview` fallback PageLabels parsing now applies the same duplicate page-index guard when native text-extractor labels are unavailable or mismatched.
- Added a focused fixture where page index `1` has a valid `Body 4` section followed by a stale duplicate `stale-duplicate-99` section, proving text extraction, `extractLabeledPageTexts()`, `openPdfSummary()`, and `getPageImagePlan()` stay aligned.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Cover-`, `Body 4`, and `App-Z` while proving `stale-duplicate-99` is excluded.

## Evidence

Red-first probe before the implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["Cover-","stale-duplicate-99","App-Z"]
MarkerAppPreview::pageLabels(...) => ["Cover-","stale-duplicate-99","App-Z"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first valid duplicate PageLabels Nums key before stale relabeling
1 test files, 168 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-nums-key-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 4","App-Z"]`, `preview_page_labels=["Cover-","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `stale_duplicate_nums_key_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `160 -> 168`
- `phpPass`: `1647 -> 1648`
- `wordpressScenarios`: `1517 -> 1518`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate page-index keys inside PageLabels `/Nums` leaves and child merge output.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
