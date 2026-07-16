# markerPDF PageLabels UTF-8 BOM fallback boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T150306Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical PDF pages, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page labels are catalog number-tree metadata whose `/Nums` values are page-label dictionaries with optional `/S`, `/P`, and `/St` fields. Existing lane notes use pypdf/PDFium page-label behavior as the native parser boundary.
- PDF 2.0 text strings may use a UTF-8 BOM. `PdfTextExtractor` already decoded UTF-8 BOM PageLabels prefixes; this slice aligns `MarkerAppPreview`'s fallback catalog PageLabels decoder with that text-string boundary.
- This stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview::decodePdfByteString()` now strips a valid UTF-8 BOM, returns the following UTF-8 text when `mb_check_encoding()` accepts it, and fails closed to an empty prefix when the BOM payload is malformed.
- Added a focused fallback fixture where the selected catalog references a missing `/Pages` object. `PdfTextExtractor::extractPageLabels()` correctly returns no labels, while `MarkerAppPreview` still inventories direct `/Page` objects and must parse catalog `/PageLabels` itself.
- The fixture proves a valid UTF-8 BOM prefix yields `R\u00e9sum\u00e9 5`, while a malformed UTF-8 BOM prefix falls back to label `9` instead of exposing mojibake or partial `Broken` prefix text.
- Added a WordPress smoke that emits preview page-break metadata from the fallback path without Python/models/external PDF tools.

## Evidence

Red-first probe before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsUtf8BomFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes UTF-8 BOM PageLabels prefixes in preview fallback before WordPress metadata
Expected: ["R\u00e9sum\u00e9 5","9"]
Actual: ["\u00ef\u00bb\u00bfR\u00c3\u00a9sum\u00c3\u00a9 5","\u00ef\u00bb\u00bf\u00c3Broken 9"]
1 test files, 3 assertions, 1 failures
```

Focused PageLabels family after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
Focused test run: 42 selected test files (root lock skipped)
42 test files, 765 assertions, 0 failures
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsUtf8BomFallbackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-utf8-bom-fallback-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors, and `git diff --check -- lanes/markerpdf` produced no output.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-utf8-bom-fallback-currentbase.php
```

The smoke exits 0 and emits `preview_page_labels=["R\u00e9sum\u00e9 5","9"]`, `text_extractor_page_labels_unavailable=true`, `valid_utf8_bom_prefix_decoded=true`, `malformed_utf8_bom_prefix_rejected=true`, `raw_bom_mojibake_excluded=true`, `malformed_prefix_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused behavior: +1 PASS case and +13 focused assertions in the direct test; the PageLabels family is now 42 files / 765 assertions / 0 failures.
- `phpPass`: `3194 -> 3195`.
- `wordpressScenarios`: `2617 -> 2618`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, or catalog `/PageLabels` keys, malformed key/value ordering, duplicate malformed values, descending/out-of-range valid-key ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed UTF-16 prefix fail-closed behavior, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, or page resource generation behavior. The bounded behavior is only UTF-8 BOM PDF text-string decoding in the `MarkerAppPreview` fallback PageLabels parser when text-extractor labels are unavailable.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, exact-generation object resolver, PageLabels number-tree parser, UTF-16/PDFDocEncoding text-string decoder, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
