# markerPDF PageLabels no-Limits kid boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T222600Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` arrays pair page-index keys with page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf documents page labels as a number-tree lookup and falls back when a label cannot be reliably determined. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This no-GPU slice preserves a malformed-PDF tolerance: when a child number-tree node has no usable `/Limits`, native extraction should not move it behind later bounded siblings, because that lets stale bounded kid labels overwrite source-order labels already aligned to page text.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now sorts kid nodes by `/Limits` only when both siblings have usable local limits. If either sibling lacks local `/Limits`, source order is preserved.
- `MarkerAppPreview::pageLabelSections()` mirrors that policy so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` remain aligned with native text extraction.
- Added a focused fixture where kid `21 0 R` has no `/Limits` and valid labels for pages 0-1, while later kid `22 0 R` has `/Limits [1 2]` with a stale page-1 label and a valid page-2 label.
- Added a WordPress smoke that emits the preserved page-break labels `Cover-`, `Body 8`, and `App-Z`.

## Evidence

Pre-edit focused probe on the same fixture:

```text
PdfTextExtractor::extractPageLabels(...) => ["Cover-","stale-limited-99","App-Z"]
MarkerAppPreview::pageLabels(...) => ["Cover-","stale-limited-99","App-Z"]
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNoLimitsKidBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps no-Limits PageLabels kids in source order before bounded stale siblings
1 test files, 11 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 345 assertions, 0 failures
```

Adjacent preview regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 110 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-no-limits-kid-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 8","App-Z"]`, `preview_page_labels=["Cover-","Body 8","App-Z"]`, `no_limits_kid_source_order_preserved=true`, `stale_limited_sibling_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2245 -> 2246`
- New focused assertions: `11`
- Focused PageLabels family: `9 test files / 345 assertions / 0 failures`
- WordPress scenarios: `1935 -> 1936`
- `mappedPdfPageLabelsNoLimitsKidCurrentBaseBehaviors`: `0 -> 1`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, null values, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, page transition/action review, or Type3/font behavior. The bounded behavior is only no-`/Limits` child nodes preserving source-order precedence before later bounded stale sibling nodes.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium raster execution, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
