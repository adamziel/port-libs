# markerPDF PageLabels descending Nums boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T112135Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through `pdftext`/PDFium before model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose leaf `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf's page-label reader records the PDF number-tree rule that `/Nums` keys are sorted numerically, then walks forward until the next key exceeds the target page. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now accepts only monotonically increasing valid page-index keys inside each PageLabels `/Nums` leaf, so a stale descending key cannot relabel an earlier physical page after a later section has been accepted.
- `MarkerAppPreview` fallback PageLabels parsing applies the same monotonic leaf-key guard, keeping preview page labels aligned with native text-extraction page-break metadata.
- Added a focused PDF fixture with `/Nums [0 Front, 2 App, 1 stale-descending, 3 End]` where page 2 must inherit `Front v` instead of using `stale-descending-99`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Front v`, `App-Z`, and `End-` while proving stale descending labels are excluded.

## Evidence

Red-first probe before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL rejects descending PageLabels Nums keys before stale relabeling
Expected: ["Front iv","Front v","App-Z","End-"]
Actual: ["Front iv","stale-descending-99","App-Z","End-"]
1 test files, 193 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects descending PageLabels Nums keys before stale relabeling
1 test files, 200 assertions, 0 failures
```

Adjacent PageLabels/preview/text-extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 938 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-descending-nums-currentbase.php
```

The smoke emits `page_labels=["Front iv","Front v","App-Z","End-"]`, `preview_page_labels=["Front iv","Front v","App-Z","End-"]`, `selected_preview_page_label="End-"`, `stale_descending_nums_key_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `192 -> 200`
- `phpPass`: `1770 -> 1771`
- `wordpressScenarios`: `1612 -> 1613`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, kid `/Limits` sorting, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` page-index keys, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only non-monotonic descending page-index keys inside a PageLabels `/Nums` leaf.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
