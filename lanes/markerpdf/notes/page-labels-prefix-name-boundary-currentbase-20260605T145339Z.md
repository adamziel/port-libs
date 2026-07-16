# markerPDF PageLabels Prefix Name Boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T145339Z`

## Source Truth

- Upstream `sddai/markerPDF` extracts searchable PDF text page-by-page through pdftext/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF PageLabels values are number-tree label dictionaries. `/P` is a text-string prefix, not a generic scalar name; valid literal and hex text strings remain accepted, while a bare name such as `/NamePrefix` is malformed and must not become a WordPress page label.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now keeps the PageLabels `/P` prefix reader strict to PDF literal and hex text strings after indirect-reference resolution.
- Bare names, dictionaries, arrays, and other non-text-string scalar values are rejected as prefixes while the surrounding `/S` style and `/St` start value still produce the correct unprefixed label.
- Added a focused PageLabels fixture and WordPress smoke proving `/P /NamePrefix /S /D /St 4` yields label `4`, not `NamePrefix4`, while page 2 still yields `Valid-8`.

## Evidence

Red-first focused run after adding the test and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL rejects PageLabels prefix name scalars before WordPress page metadata
Expected: ["4","Valid-8"]
Actual:   ["NamePrefix4","Valid-8"]
1 test files, 225 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 232 assertions, 0 failures
```

Adjacent PageLabels and marker-app preview gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
3 test files, 356 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-prefix-name-boundary-currentbase.php
```

The smoke emits `page_labels=["4","Valid-8"]`, `preview_page_labels=["4","Valid-8"]`, `selected_preview_page_label="Valid-8"`, `name_prefix_scalar_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `224 -> 232`.
- Focused PASS cases: `+1`.
- `phpPass`: `2010 -> 2011`.
- `wordpressScenarios`: `1742 -> 1743`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed indirect `/P` prefix scalars with extra tokens, malformed `/S` style scalars, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, duplicate `/Nums` keys inside one leaf, out-of-order kid merge by `/Limits`, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only bare-name `/P` prefix scalar rejection.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, indirect-reference resolver, top-level PDF value tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
