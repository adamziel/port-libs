# markerPDF PageLabels dictionary object boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T172607Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- pypdf's page-label helper documents `/PageLabels` as a number tree whose `/Nums` array pairs integer page-index keys with value objects, and falls back to physical page labels when the resolved value is not a dictionary. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This malformed-boundary slice keeps indirect PageLabels dictionary objects strict: comments are PDF whitespace, but extra non-comment tokens after an otherwise valid dictionary object make the PageLabels node/value malformed for native WordPress page-break metadata.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now requires resolved PageLabels number-tree child nodes and label dictionaries to be a single dictionary object with only PDF whitespace or comments after the `>>` token.
- `MarkerAppPreview` applies the same single-dictionary boundary in fallback PageLabels parsing, keeping `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` aligned with native text extraction.
- Added a focused fixture where `/Nums [0 30 0 R 1 31 0 R]` resolves `30 0 obj` to `<< /P (Bad-) /S /D /St 4 >> /Private`; that malformed value now falls back to physical label `1`, while `31 0 obj` with a comment-only tail remains `Valid-8`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `1` and `Valid-8` while proving `Bad-4` stays excluded.

## Evidence

Red-first probe before source edits:

```text
PdfTextExtractor::extractPageLabels(...) => ["Bad-4","Valid-8"]
MarkerAppPreview::pageLabels(...) => ["Bad-4","Valid-8"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed PageLabels dictionary object tails before WordPress page metadata
1 test files, 248 assertions, 0 failures
```

Adjacent PageLabels/preview/text extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1000 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-dictionary-object-boundary-currentbase.php
```

The smoke emits `page_labels=["1","Valid-8"]`, `preview_page_labels=["1","Valid-8"]`, `summary_page_labels=["1","Valid-8"]`, `selected_preview_page_label="Valid-8"`, `malformed_dictionary_object_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `240 -> 248`.
- Focused PASS cases: `+1`.
- `phpPass`: `2108 -> 2109`.
- `wordpressScenarios`: `1818 -> 1819`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed prefix/style scalar tails, bare-name prefix rejection, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, duplicate `/Nums` keys, descending `/Nums` keys, out-of-order kid merge by `/Limits`, same-lower sibling limits, mixed `/Nums` plus `/Kids`, malformed value ordering, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only resolved PageLabels dictionary objects whose object body has extra non-comment tokens after the dictionary token.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
