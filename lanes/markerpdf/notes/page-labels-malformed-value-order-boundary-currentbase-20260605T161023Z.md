# markerPDF PageLabels malformed value order boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T161023Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through `pdftext`/PDFium before model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose leaf `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf's page-label reader records the PDF number-tree rule that `/Nums` keys are sorted numerically, then walks forward until the next key exceeds the target page. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now records the latest syntactically valid PageLabels `/Nums` key before parsing the paired label dictionary value, so a malformed value still preserves the sorted-key boundary.
- `MarkerAppPreview` fallback PageLabels parsing applies the same boundary when native text-extractor labels are unavailable or mismatched.
- Added a focused fixture with `/Nums [0 Cover, 3 malformed-value, 2 stale-lower]` in a four-page document. Pages 2-4 must inherit `Cover-`; they must not become `stale-lower-99`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for four `Cover-` pages while proving stale lower labels are excluded.

## Evidence

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed PageLabels values as ordering boundaries before stale lower keys
1 test files, 240 assertions, 0 failures
```

Adjacent PageLabels/preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 350 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-value-order-currentbase.php
```

The smoke emits `page_labels=["Cover-","Cover-","Cover-","Cover-"]`, `preview_page_labels=["Cover-","Cover-","Cover-","Cover-"]`, `selected_preview_page_label="Cover-"`, `malformed_value_boundary_preserved=true`, `stale_lower_key_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `232 -> 240` after adding this focused PASS case.
- `phpPass`: `2064 -> 2065`
- `wordpressScenarios`: `1781 -> 1782`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, kid `/Limits` sorting, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` page-index keys, descending in-range `/Nums` keys, same-lower sibling limits, mixed `/Nums` plus `/Kids`, out-of-range valid-key ordering, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only a lower stale `/Nums` key after a higher syntactically valid key whose paired label value is malformed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
