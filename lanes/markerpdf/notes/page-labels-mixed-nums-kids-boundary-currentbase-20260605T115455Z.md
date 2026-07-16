# markerPDF PageLabels mixed Nums/Kids boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T115455Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through pdftext/PDFium before model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This malformed-boundary slice covers a page-label node that has both direct `/Nums` and bounded `/Kids`. Valid direct `/Nums` sections remain first, but their presence no longer short-circuits child traversal, so later kid ranges still label their physical pages.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now starts with valid direct `/Nums` entries and then traverses `/Kids`, preserving the first valid page-index section after limit filtering.
- `MarkerAppPreview` fallback PageLabels parsing now mirrors that mixed-node merge so preview/page-image metadata stays aligned when native text-extractor labels are unavailable or mismatched.
- Added focused coverage where a malformed intermediate `/PageLabels` node contains `/Nums` for page `0`, an out-of-range stale page `4` section, and child nodes for pages `1` through `3`.
- Added a WordPress smoke that emits page-break metadata for `Cover-`, `Body 8`, `App-Z`, and `End-` while proving `stale-root-99` is excluded.

## Evidence

Red-first probe before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL keeps PageLabels Kids after direct Nums on malformed intermediate nodes
Expected: ['Cover-', 'Body 8', 'App-Z', 'End-']
Actual: ['Cover-', 'Cover-', 'Cover-', 'Cover-']
1 test files, 201 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps PageLabels Kids after direct Nums on malformed intermediate nodes
1 test files, 208 assertions, 0 failures
```

Adjacent preview/text extractor regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 946 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-mixed-nums-kids-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 8","App-Z","End-"]`, `preview_page_labels=["Cover-","Body 8","App-Z","End-"]`, `selected_preview_page_label="End-"`, `kids_after_nums_preserved=true`, `stale_root_nums_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `201 -> 208` for the selected PageLabels file.
- `phpPass`: unchanged; this adds assertions to an existing focused test file, not a new test file.
- `wordpressScenarios`: `1633 -> 1634` via `wordpress-pdf-page-labels-mixed-nums-kids-currentbase.php`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` keys, descending `/Nums` keys, kid `/Limits` ordering, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only preserving bounded child traversal after direct `/Nums` on the same malformed PageLabels node.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
