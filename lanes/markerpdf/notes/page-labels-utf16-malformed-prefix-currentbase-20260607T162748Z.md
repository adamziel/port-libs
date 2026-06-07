# markerPDF PageLabels UTF-16 malformed prefix boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260607T162748Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` values are page-label dictionaries with optional `/S`, `/P`, and `/St` fields. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- PDF text strings with UTF-16 BOMs should decode only when the BOM payload is well-formed UTF-16. Adjacent native markerPDF extractors already fail closed on odd-length or invalid UTF-16 metadata/action strings before calling `iconv`.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::decodePdfTextStringBytes()` now validates BOM-prefixed UTF-16BE/UTF-16LE payloads with byte-pair and `mb_check_encoding()` guards before decoding PageLabels prefix text strings.
- `MarkerAppPreview::decodePdfByteString()` mirrors the same guard for its fallback PageLabels preview decoder.
- Added a focused two-page fixture where malformed prefix bytes `<FEFF0041D8000042>` previously decoded as `AB` through `iconv(...//IGNORE)`, but must now fail closed to an empty prefix while a valid UTF-16 prefix still yields `Valid-8`.
- Added a WordPress smoke proving Gutenberg page-break labels are `4` and `Valid-8`, with no Python/model/raster/external-tool execution.

## Evidence

Red-first focused run on accepted base `1d69a68f53ce21789449f52c6103c11f01fcd7a9`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsUtf16MalformedPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on malformed UTF-16 PageLabels prefixes before WordPress page metadata
Expected: ["4","Valid-8"]
Actual: ["AB4","Valid-8"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsUtf16MalformedPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed UTF-16 PageLabels prefixes before WordPress page metadata
1 test files, 13 assertions, 0 failures
```

Adjacent PageLabels and preview regression run:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*Test\.php$' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 29 selected test files (root lock skipped)
29 test files, 695 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-utf16-malformed-prefix-currentbase.php > /tmp/markerpdf-page-labels-utf16-malformed-prefix.html
```

The smoke exits 0 and emits `page_labels=["4","Valid-8"]`, `preview_page_labels=["4","Valid-8"]`, `selected_preview_page_label="Valid-8"`, `malformed_utf16_prefix_rejected=true`, `valid_utf16_prefix_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels behavior: +1 PASS case and +13 focused assertions.
- `phpPass`: `2913 -> 2914`.
- `wordpressScenarios`: `2429 -> 2430`.
- `mappedPdfPageLabelsUtf16MalformedPrefixCurrentBaseBehaviors`: `0 -> 1`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, malformed key/value ordering, duplicate malformed values, descending or out-of-range valid-key ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed UTF-16 BOM `/P` prefix strings in PageLabels value dictionaries failing closed before partial prefix text can relabel WordPress page breaks.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, exact-generation object resolver, PageLabels number-tree parser, UTF-16/PDFDocEncoding text-string decoder, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
