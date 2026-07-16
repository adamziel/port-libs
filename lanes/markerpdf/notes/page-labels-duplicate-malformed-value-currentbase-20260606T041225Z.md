# markerPDF PageLabels duplicate malformed-value boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T041225Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` arrays pair page-index keys with page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- Existing accepted markerPDF PageLabels slices already keep later lower keys blocked after syntactically valid higher keys, and keep the first valid duplicate key before stale duplicate relabeling. This slice covers the missing in-between boundary: a malformed duplicate value for a page key must not block the first later usable value with that same key.

## Implementation

- `PdfTextExtractor::pageLabelNumsEntriesFromArray()` now tracks the latest syntactically seen `/Nums` page key separately from accepted label entries. Later lower keys remain blocked, but equal duplicate keys can continue until a usable section is accepted.
- `MarkerAppPreview::pageLabelSectionsFromNums()` mirrors the same boundary so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` stay aligned.
- Added a focused fixture where `/Nums [0 malformed 0 Cover 0 stale 1 Body 2 App]` must emit `Cover-`, `Body 4`, and `App-Z` while excluding the later stale duplicate.
- Added a WordPress smoke that emits Gutenberg page-break metadata with the recovered labels and reports no Python/model/external PDF tooling.

## Evidence

Red-first probe before source edit:

```text
php -r '<focused duplicate malformed-value PageLabels probe>'
[["1","Body 4"],["1","Body 4"]]
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateMalformedValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first usable duplicate PageLabels key after malformed duplicate value

1 test files, 9 assertions, 0 failures
```

Adjacent PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 404 assertions, 0 failures
```

Preview regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 110 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-malformed-value-currentbase.php
```

The smoke emits `first_usable_duplicate_value_preserved=true`, `stale_later_duplicate_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsDuplicateMalformedValueBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-malformed-value-currentbase.php
git diff --check -- lanes/markerpdf
```

All returned clean.

## Delta

- Focused PHP PASS cases: `2382 -> 2383`
- Focused new assertions: `9`
- Focused PageLabels family: `14 test files / 404 assertions / 0 failures`
- WordPress scenarios: `2037 -> 2038`
- `markerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- `pdfPageLabelsDuplicateMalformedValueCurrentBaseBehaviors`: `0 -> 1`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate valid `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, null values, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate same-page `/Nums` keys where malformed same-key values precede the first usable label dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium raster execution, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
