# markerPDF PageLabels array object boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T180328Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` arrays pair integer page-index keys with page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This malformed-boundary slice keeps indirect PageLabels arrays strict: comments are PDF whitespace, but extra non-comment tokens after an otherwise valid array object make that `/Nums`, `/Kids`, or `/Limits` array malformed for native WordPress page-break metadata.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now requires resolved PageLabels `/Nums`, `/Kids`, and `/Limits` array objects to be a single array token with only PDF whitespace or comments after the closing `]`.
- `MarkerAppPreview` applies the same single-array boundary in fallback PageLabels parsing, keeping `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` aligned with native text extraction.
- Added a focused fixture where one child node resolves `/Nums 30 0 R` to `[0 << /P (BadArray-) /S /D /St 99 >>] /Private`; that malformed array is rejected, while a sibling comment-only array tail remains valid and yields `Body 4` and `App-Z`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `1`, `Body 4`, and `App-Z` while proving `BadArray-99` stays excluded.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed PageLabels array object tails before WordPress page metadata
Expected: ["1","Body 4","App-Z"]
Actual: ["BadArray-99","Body 4","App-Z"]
1 test files, 249 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed PageLabels array object tails before WordPress page metadata
1 test files, 256 assertions, 0 failures
```

Adjacent PageLabels/preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 380 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-array-object-boundary-currentbase.php
```

The smoke emits `page_labels=["1","Body 4","App-Z"]`, `preview_page_labels=["1","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `malformed_array_object_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `248 -> 256`.
- Focused PASS cases: `+1`.
- `phpPass`: `2129 -> 2130`.
- `wordpressScenarios`: `1835 -> 1836`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, malformed `/Limits` extra operands, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed prefix/style scalar tails, malformed dictionary object tails, bare-name prefix rejection, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key generation handling, indirect `/Nums` array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` keys, descending `/Nums` keys, out-of-order kid merge by `/Limits`, same-lower sibling limits, mixed `/Nums` plus `/Kids`, malformed value ordering, out-of-range key ordering, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only resolved PageLabels array objects whose object body has extra non-comment tokens after the array token.

## Exclusion

An optional broader run that included `lanes/markerpdf/tests/PdfTextExtractorTest.php` was not used as acceptance evidence because current base still reports two unrelated ToUnicode `usecmap` failures outside this PageLabels array-object slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 1005 assertions, 2 failures
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
