# markerPDF PageLabels negative kid Limits boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T084301Z`

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page through `pdftext`/PDFium before OCR/layout/model execution; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a number tree keyed by zero-based physical page index. pypdf documents that page indexes start at 0 and that `/Nums` keys are sorted integer keys. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now treats a two-integer `/Limits` range with either bound below zero as an invalid PageLabels number-tree range, matching the existing fail-closed handling for reversed ranges.
- `MarkerAppPreview::pageLabelSections()` applies the same guard so fallback preview inventory remains aligned with native text labels.
- Added a focused four-page fixture where a first kid claims `/Limits [-1 2]` and tries to label pages with `stale-underflow-*`, while a later valid `[0 2]` kid supplies `Front iv`, `Front v`, and `App-Z`.
- Added a WordPress smoke that emits Gutenberg page-break metadata while proving the negative kid labels remain excluded.

## Evidence

Red-first in-memory probe before source edit:

```text
Expected labels: ["Front iv","Front v","App-Z","End-"]
Actual labels:   ["stale-underflow-77","stale-underflow-78","stale-app-Z","End-"]
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNegativeKidLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects negative PageLabels kid Limits before stale range claims
1 test files, 14 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 565 assertions, 0 failures
```

Extractor-focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 629 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-negative-kid-limits-currentbase.php
```

The smoke reports `negative_kid_limits_rejected=true`, `later_valid_kid_preserved=true`, `later_non_overlapping_kid_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsNegativeKidLimitsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-negative-kid-limits-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON ok\n";'
git diff --check -- lanes/markerpdf
```

All returned clean.

## Status Delta

- Focused PHP PASS cases: `2474 -> 2475`
- Focused PageLabels assertions: new test adds `14` assertions
- WordPress scenarios: `2107 -> 2108`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed `/Limits`, no-limits kid source order, disjoint or overlapping kid sorting, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only negative kid `/Limits` underflow before a stale child can claim physical page indexes or suppress a later valid child.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
