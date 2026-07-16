# MarkerPDF PageLabels type boundary current-base

- Slice: `markerpdf-page-labels-boundary-current-base-20260608T122927Z`
- Base accepted HEAD: `03d7c4f1ec7ff6e233514aae2d1542db24fa965c`
- Scope: native no-GPU PageLabels parsing for searchable PDFs and WordPress page-break metadata.

## Source truth

PDF PageLabels are catalog number-tree entries whose `/Nums` values are page-label dictionaries. A page-label dictionary's optional `/Type` name is valid when it is `/PageLabel`; other dictionary types should not relabel pages. Upstream markerPDF's searchable-PDF path extracts page text before model work, and the native PHP port keeps PageLabels as page-break and preview metadata aligned to physical pages.

Reference source truth:

- Upstream markerPDF searchable text extraction entrypoint: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel unit coverage for catalog PageLabels number-tree behavior: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp

This is bounded parser behavior. No OCR, Surya/Texify/Torch, model execution, GPU work, raster rendering, JavaScript, form actions, or external PDF tools were used.

## Implementation

- `PdfTextExtractor` now validates prospective PageLabels value dictionaries before accepting a `/Nums` entry. Missing `/Type` remains accepted, `/Type /PageLabel` remains accepted, indirect `/Type /PageLabel` remains accepted, and other `/Type` names are rejected before they can claim the page index.
- `MarkerAppPreview` mirrors the same guard so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` stay aligned with extracted labeled page text.
- Duplicate same-page recovery is preserved: a wrong-type value dictionary is skipped, allowing the later valid same-key PageLabel dictionary to provide the label.

## Red-first evidence

Before the source patch, the focused test failed because a `/Type /Pages` label value dictionary relabeled the first page before a later valid duplicate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects wrong PageLabels value dictionary Type before stale WordPress page metadata
Expected: ['Cover-', 'Body 4', 'App-Z']
Actual: ['stale-type-77', 'Body 4', 'App-Z']
1 test files, 1 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects wrong PageLabels value dictionary Type before stale WordPress page metadata

1 test files, 17 assertions, 0 failures
```

Adjacent PageLabels current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
Focused test run: 41 selected test files (root lock skipped)
41 test files, 760 assertions, 0 failures
```

Preview regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 110 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-type-boundary-currentbase.php
```

The smoke emits `wrong_type_dictionary_rejected=true`, `indirect_page_label_type_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsTypeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-type-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All returned clean.

## WordPress smoke

Added `examples/wordpress-pdf-page-labels-type-boundary-currentbase.php`. The smoke emits native WordPress page-break blocks with labels `Cover-`, `Body 4`, and `App-Z` and asserts stale wrong-type labels such as `stale-type-77` and `stale-type-78` are excluded. It also reports `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Status delta

- Focused PHP PASS cases: `3090 -> 3091`
- Focused new assertions: `17`
- WordPress-relevant scenarios: `2549 -> 2550`
- Mapped upstream denominator: unchanged; this is an additive boundary case inside the already mapped PageLabels parser cluster.

## Non-overlap

This avoids accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, malformed value ordering, duplicate malformed values, node-shaped value dictionaries, descending or out-of-range valid-key ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, and comment-boundary parsing. The bounded behavior is only optional PageLabel dictionary `/Type` validation.

## Dependency closure

No new support component is needed. The implementation reuses the existing native PDF object resolver, balanced dictionary reader, indirect-name resolver, top-level PageLabels value scanners, MarkerAppPreview inventory path, and WordPress block smoke path.
