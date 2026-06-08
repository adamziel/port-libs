# markerPDF PageLabels empty hex prefix fallback boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T180724Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical PDF pages, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page labels are catalog number-tree metadata whose `/Nums` values are page-label dictionaries with optional `/S`, `/P`, and `/St` fields. `/P` is a text string, and an empty hex string `<>` is a valid empty text string prefix.
- `PdfTextExtractor` already accepted empty hex text strings. This slice aligns `MarkerAppPreview`'s fallback catalog PageLabels decoder with that text-string boundary when text-extractor labels are unavailable.
- This stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview::decodePdfStringValue()` now accepts zero-length hex PDF strings by allowing an empty hex body in the text-string regex.
- Added a focused fallback fixture where the selected catalog references a missing `/Pages` object. `PdfTextExtractor::extractPageLabels()` correctly returns no labels, while `MarkerAppPreview` inventories direct `/Page` objects and must parse catalog `/PageLabels` itself.
- The fixture proves `/P <>` is treated as a usable empty prefix before a later stale duplicate `/P (stale-)`, producing label `4` rather than `stale-4`.
- Added a WordPress smoke that emits preview page-break metadata from the fallback path without Python/models/external PDF tools.

## Evidence

Red-first probe before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsEmptyHexPrefixFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps empty hex PageLabels prefixes usable in preview fallback before stale duplicates
Expected: array (
  0 => '4',
  1 => '8',
)
Actual: array (
  0 => 'stale-4',
  1 => '8',
)
1 test files, 3 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsEmptyHexPrefixFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps empty hex PageLabels prefixes usable in preview fallback before stale duplicates
1 test files, 11 assertions, 0 failures
```

Focused PageLabels family after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
Focused test run: 46 selected test files (root lock skipped)
46 test files, 835 assertions, 0 failures
```

Adjacent preview test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 110 assertions, 0 failures
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsEmptyHexPrefixFallbackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-empty-hex-prefix-fallback-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors, and `git diff --check -- lanes/markerpdf` produced no output.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-empty-hex-prefix-fallback-currentbase.php
```

The smoke exits 0 and emits `preview_page_labels=["4","8"]`, `text_extractor_page_labels_unavailable=true`, `empty_hex_prefix_kept=true`, `empty_literal_prefix_kept=true`, `stale_duplicate_prefixes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused behavior: +1 PASS case and +11 focused assertions in the direct test; the PageLabels family is now 46 files / 835 assertions / 0 failures.
- `phpPass`: `3360 -> 3361`.
- `wordpressScenarios`: `2737 -> 2738`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, or catalog `/PageLabels` keys, malformed key/value ordering, duplicate malformed values, descending/out-of-range valid-key ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, UTF-8 BOM prefixes, malformed UTF-16 prefix fail-closed behavior, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, or page resource generation behavior. The bounded behavior is only empty hex PDF text-string prefix handling in the `MarkerAppPreview` fallback PageLabels parser when text-extractor labels are unavailable.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, exact-generation object resolver, PageLabels number-tree parser, existing text-string decoder, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
