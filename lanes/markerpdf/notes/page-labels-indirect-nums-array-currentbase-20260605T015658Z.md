# markerPDF PageLabels indirect Nums array boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T015658Z`

## Source Truth

- Upstream markerPDF obtains searchable PDF text by iterating page boundaries before conversion. Native PHP `/PageLabels` stay page-break and preview metadata aligned with page-local text, not visible body text.
- PDF catalog `/PageLabels` is a number tree. The `/Nums` value is an array object and may be indirect; an indirect reference such as `30 0 R` must resolve by exact object generation before accepting page-label sections.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pdfArrayFromValue()` now resolves indirect array operands by exact object generation instead of binding arrays by object number only.
- `MarkerAppPreview::pageLabelSections()` now resolves indirect `/Nums` array objects before tokenizing PageLabels key/value entries, matching the existing extractor behavior.
- Added a focused fixture where `/PageLabels << /Nums 30 0 R >>` points at the current array while `30 1 obj` contains stale label sections. Text extraction and preview both preserve `Cover-`, `Body 7`, and `App-Z`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for the current labels while proving the stale higher-generation `/Nums` array is excluded.

## Evidence

Red-first probe before source edits:

```text
PdfTextExtractor::extractPageLabels(...) => ["stale-array-99","stale-array-100"]
MarkerAppPreview::pageLabels(...) => ["1","2"]
Expected current labels: ["Cover-","Body 7"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect PageLabels Nums arrays by exact generation for preview metadata
1 test files, 70 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-indirect-nums-array-currentbase.php
```

The smoke emitted `page_labels=["Cover-","Body 7","App-Z"]`, `preview_page_labels=["Cover-","Body 7","App-Z"]`, `selected_preview_page_label="App-Z"`, `stale_higher_generation_nums_array_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1268 -> 1269`
- `wordpressScenarios`: `1233 -> 1234`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct `/Nums`, direct or indirect `/Kids`, inherited/local `/Limits`, indirect `/Limits` operands, indirect `/Nums` key operands, indirect `/S` `/P` `/St` label operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is exact-generation resolution for an indirect PageLabels `/Nums` array object and preview alignment.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
