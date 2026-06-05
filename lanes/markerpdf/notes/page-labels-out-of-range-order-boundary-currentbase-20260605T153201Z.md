# markerPDF PageLabels out-of-range ordering boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T153201Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through `pdftext`/PDFium before model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose leaf `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf's page-label reader records the PDF number-tree rule that `/Nums` keys are sorted numerically, then walks forward until the next key exceeds the target page. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now records the latest syntactically valid PageLabels `/Nums` key before page-count and inherited-limit filtering, so an out-of-range high key still acts as a number-tree ordering boundary.
- `MarkerAppPreview` fallback PageLabels parsing applies the same boundary when native text-extractor labels are unavailable or mismatched.
- Added a focused fixture with `/Nums [0 Front, 2 stale-out-of-range, 1 stale-late]` in a two-page document. Page 2 must inherit `Front v`; it must not become `stale-late-77`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv` and `Front v` while proving stale out-of-range/lower labels are excluded.

## Evidence

Red-first probe before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsOutOfRangeOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects lower PageLabels Nums keys after out-of-range ordering boundaries
Expected: ["Front iv","Front v"]
Actual: ["Front iv","stale-late-77"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsOutOfRangeOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects lower PageLabels Nums keys after out-of-range ordering boundaries
1 test files, 10 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsOutOfRangeOrderBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 256 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-out-of-range-order-currentbase.php
```

The smoke emits `page_labels=["Front iv","Front v"]`, `preview_page_labels=["Front iv","Front v"]`, `selected_preview_page_label="Front v"`, `out_of_range_boundary_rejected=true`, `lower_stale_key_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `246 -> 256` for the selected three-file family after adding this focused PASS case.
- `phpPass`: `2035 -> 2036`
- `wordpressScenarios`: `1761 -> 1762`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, kid `/Limits` sorting, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` page-index keys, descending in-range `/Nums` keys, same-lower sibling limits, mixed `/Nums` plus `/Kids`, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only a lower in-range stale `/Nums` key after a higher out-of-range key inside the same PageLabels leaf.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
