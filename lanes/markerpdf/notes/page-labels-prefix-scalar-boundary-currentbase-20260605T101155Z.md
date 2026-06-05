# markerPDF PageLabels Prefix Scalar Boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T101155Z`

## Source Truth

- Upstream `sddai/markerPDF` extracts searchable PDF text page-by-page through pdftext/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` entries are number-tree values whose label dictionary `/P` operand is a text string prefix. Like the existing `/S`, `/St`, `/Nums`, and `/Limits` scalar handling, an indirect prefix object must resolve to one scalar token; comments are whitespace, but extra non-comment tokens make the prefix malformed.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now decodes PageLabels `/P` prefixes through a single-token text-string boundary after resolving indirect objects.
- Comment-only tails remain valid PDF whitespace, preserving the accepted scalar-comment behavior.
- Malformed prefix objects such as `(Malformed-) /S /D` are rejected as prefixes while the valid `/S /D /St 4` label range still produces unprefixed label `4`.
- Added a WordPress smoke that renders page-break metadata for labels `4` and `Valid-8`, and proves `Malformed-4` is excluded from import and preview metadata.

## Evidence

Red-first focused run after adding the test and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL rejects malformed PageLabels prefix scalar tokens before WordPress page metadata
Expected: ["4","Valid-8"]
Actual:   ["Malformed-4","Valid-8"]
1 test files, 177 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 184 assertions, 0 failures
```

Adjacent PageLabels and marker-app preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
3 test files, 308 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-prefix-scalar-boundary-currentbase.php
```

The smoke emits `page_labels=["4","Valid-8"]`, `preview_page_labels=["4","Valid-8"]`, `selected_preview_page_label="Valid-8"`, `malformed_prefix_scalar_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `176 -> 184`.
- Focused PASS cases: `+1`.
- `phpPass`: `1707 -> 1708`.
- `wordpressScenarios`: `1564 -> 1565`.
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, duplicate `/Nums` keys inside one leaf, out-of-order kid merge by `/Limits`, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed indirect `/P` prefix scalar objects with extra non-comment tokens after an otherwise valid text string.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, indirect-reference resolver, top-level PDF value tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
