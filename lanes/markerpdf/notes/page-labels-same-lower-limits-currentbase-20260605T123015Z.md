# markerPDF PageLabels same-lower kid Limits boundary

## Source truth

- Upstream markerPDF extracts page-local text through PDF pages before model execution; native PHP PageLabels remain document/page-break review metadata aligned to those physical pages under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. pypdf's page-label helper documents sorted `/Nums` keys and descends `/Kids` by `/Limits` membership (<https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/>), while PDFium's `CPDF_PageLabel` tests model PageLabels as a number tree of page-index keys and label dictionaries (<https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp>).
- This slice keeps the accepted native repair for out-of-order kid lower bounds, but prevents a malformed later sibling kid with the same lower `/Limits` bound from shadowing an earlier source-order sibling's label range.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now sorts kid nodes by lower `/Limits` bound and source order only, not by upper bound when the lower bound ties.
- Same-lower sibling kid ranges that declare local `/Limits` are tracked during merge, so later stale same-lower kids cannot insert sections inside an earlier kid's declared range.
- `MarkerAppPreview` applies the same merge behavior so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` remain aligned with native text extraction.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL preserves PageLabels kid source order when sibling Limits share the same lower bound
Actual: ["stale-same-lower-99","stale-same-lower-body-100","App-Z","End-"]
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 216 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-same-lower-limits-currentbase.php
```

The smoke reports `same_lower_source_order_preserved=true`, `stale_same_lower_kid_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PageLabels assertions: `208 -> 216`
- Focused PHP PASS cases: `1822 -> 1823`
- WordPress scenarios: `1656 -> 1657`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`

## Non-overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited or indirect `/Limits`, malformed `/Limits`, escaped catalog names, PDFDocEncoding prefixes, alphabetic style formatting, indirect scalar operands, generation-exact values/keys, object-stream PageLabels, duplicate `/Nums` keys, descending `/Nums` keys, out-of-order kid lower-bound sorting, mixed root `/Nums` plus `/Kids`, trailer `/Root` selection, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is only same-lower sibling kid `/Limits` source-order preservation.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
