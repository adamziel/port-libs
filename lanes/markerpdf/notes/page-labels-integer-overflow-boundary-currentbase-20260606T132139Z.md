# markerPDF PageLabels integer overflow boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T132139Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical pages, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF PageLabels are number-tree entries keyed by page-index integers. pypdf documents `/Nums` as sorted integer keys with associated values, and PDFium PageLabel tests model `/S`, `/P`, and `/St`, with `/St` required to be at least `1` when specified. Sources: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py and https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pikepdf exposes PDF number trees as integer-keyed mappings for `/PageLabels`, so unrepresentable integer operands should fail closed instead of being truncated by PHP casts. Source: https://pikepdf.readthedocs.io/en/latest/api/models.html
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python models, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now bounds PageLabels integer scalar parsing before casting to PHP integers.
- `MarkerAppPreview` applies the same bounded integer parser for fallback PageLabels `/Nums` keys, `/Limits`, and `/St` values, keeping preview and extractor labels aligned.
- Added focused coverage where an overlarge `/Nums` key no longer becomes a false ordering boundary, an overlarge `/Limits` range is ignored as malformed instead of clipping valid labels, and an overlarge `/St` defaults to `1`.
- Added a WordPress smoke proving page-break labels remain `Cover-`, `Body 1`, and `App-Z`, while stale overflow labels and integer-cast labels are excluded.

## Evidence

Pre-implementation probe on the accepted base with an overlarge `/Nums` key:

```text
PdfTextExtractor::extractPageLabels($pdf) => ["Cover-","Cover-"]
MarkerAppPreview::pageLabels($pdf) => ["Cover-","Cover-"]
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsIntegerOverflowBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overlarge PageLabels integer operands before WordPress page metadata
1 test files, 17 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 22 selected test files (root lock skipped)
22 test files, 608 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-integer-overflow-currentbase.php > /tmp/markerpdf-page-labels-integer-overflow.html
```

The smoke emits `page_labels=["Cover-","Body 1","App-Z"]`, `preview_page_labels=["Cover-","Body 1","App-Z"]`, `selected_preview_page_label="Body 1"`, `overlarge_nums_key_rejected=true`, `overlarge_limits_ignored=true`, `overlarge_start_defaulted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: new file adds `17` assertions.
- Focused PASS files: `+1`.
- `phpPass`: `2557 -> 2558`.
- `wordpressScenarios`: `2171 -> 2172`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits` dictionary or extra-operand handling, negative kid `/Limits`, reversed root/kid `/Limits`, indirect `/Limits`, indirect `/Nums` key/array generation handling, signed integer parsing, duplicate `/Nums` or `/Kids` keys, descending/out-of-range `/Nums` ordering, mixed `/Nums` plus `/Kids`, scalar comments, escaped names, PDFDocEncoding prefixes, alphabetic/roman formatting, generation-exact dictionaries, object-stream PageLabels, encrypted preview suppression, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only overlarge unrepresentable integer operands before PageLabels number-tree ordering, range clipping, and start-number parsing.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary/image-plan path, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
