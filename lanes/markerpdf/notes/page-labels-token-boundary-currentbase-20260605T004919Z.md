# markerPDF PageLabels token boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T004919Z`

## Source Truth

- Upstream markerPDF gets searchable PDF text through page-scoped pdftext/PDFium extraction before model execution; native PHP `/PageLabels` remain page-break and preview metadata aligned with page-local text, not visible body text.
- PDF catalog `/PageLabels` is a number tree. The catalog reference carries an object generation, `/Kids` arrays contain top-level kid references, and `/Nums` arrays contain top-level key/value pairs. Nested dictionaries, arrays, comments, and strings inside those arrays are not number-tree entries.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now resolves the catalog `/PageLabels` dictionary through generation-exact indirect references instead of binding by object number only.
- `PdfTextExtractor` now parses PageLabels `/Kids` through top-level array tokens and preserves kid object generations when recursing through number-tree nodes.
- `PdfTextExtractor` now parses `/Nums` as top-level array key/value pairs, so nested private arrays, dictionaries, comments, strings, and reference-looking tokens cannot inject stale page-label sections.
- Added a focused fixture with a stale higher-generation root PageLabels object, a nested `/Kids` private dictionary decoy, and a nested `/Nums` array decoy. Text extraction and `MarkerAppPreview` both preserve `Cover-` and `Body 4`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for the valid labels while proving stale root, nested kid, and nested `/Nums` labels are excluded.

## Evidence

Red-first one-off probes before the implementation:

```text
PdfTextExtractor::extractPageLabels(...) with nested Kids/Nums decoys => ["Cover-","kid-stale-77"]
MarkerAppPreview::pageLabels(...) => ["Cover-","Body 4"]

PdfTextExtractor::extractPageLabels(...) with /PageLabels 20 0 R plus 20 1 stale decoy => ["stale-99","stale-100"]
MarkerAppPreview::pageLabels(...) => ["Real-","Body 4"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 55 assertions, 0 failures
```

Adjacent extractor/preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
3 test files, 793 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-token-boundary-currentbase.php
```

The smoke emitted `page_labels=["Cover-","Body 4"]`, `preview_page_labels=["Cover-","Body 4"]`, `selected_preview_page_label="Body 4"`, `stale_root_generation_excluded=true`, `nested_kid_references_excluded=true`, `nested_nums_entries_excluded=true`, and execution flags false.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, inherited/local `/Limits`, indirect `/Limits` operands, indirect `/S` `/P` `/St` label operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact indirect value dictionaries, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is generation-exact root `/PageLabels` references plus top-level-only `/Kids` and `/Nums` number-tree token parsing.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, generation-indexed object body table, top-level dictionary value reader, PageLabels formatter, marker-app preview summary, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
