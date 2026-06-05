# markerPDF PageLabels style scalar boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T104615Z`

## Source Truth

- Upstream `sddai/markerPDF` extracts searchable PDF text page-by-page through pdftext/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel tests model `/PageLabels` as a number tree where `/Nums` keys are page indexes and values are page-label dictionaries with optional `/S`, `/P`, and `/St` operands. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This malformed-boundary slice keeps indirect `/S` style operands scalar-token strict: comments are PDF whitespace, but extra non-comment tokens after a valid name make the style malformed.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now requires PageLabels `/S` style names to resolve to exactly one PDF name token, with only whitespace or comments allowed after the name.
- Comment-only tails remain valid, preserving the accepted scalar-comment boundary for indirect `/S`, `/P`, and `/St` operands.
- Added a focused fixture where `/S 30 0 R` resolves to `/D /Private`; the malformed style is rejected so page 1 stays prefix-only `Bad-` instead of becoming stale numeric label `Bad-4`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Bad-` and `Valid-8` while proving `Bad-4` is excluded from import and preview metadata.

## Evidence

Red-first probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["Bad-4","Valid-8"]
MarkerAppPreview::pageLabels(...) => ["Bad-4","Valid-8"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed PageLabels style scalar tokens before WordPress page metadata
1 test files, 192 assertions, 0 failures
```

Adjacent scalar-comment and preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 124 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-style-scalar-boundary-currentbase.php
```

The smoke emits `page_labels=["Bad-","Valid-8"]`, `preview_page_labels=["Bad-","Valid-8"]`, `selected_preview_page_label="Valid-8"`, `malformed_style_scalar_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `184 -> 192`.
- Focused PASS cases: `+1`.
- `phpPass`: `1740 -> 1741`.
- `wordpressScenarios`: `1587 -> 1588`.
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed indirect `/P` prefix scalars, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, duplicate `/Nums` keys inside one leaf, out-of-order kid merge by `/Limits`, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed indirect `/S` style scalar objects with extra non-comment tokens after an otherwise valid name.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, indirect-reference resolver, top-level PDF value tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
