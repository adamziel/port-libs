# markerPDF PageLabels malformed key resync boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260607T121726Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` keys are page indices and values are page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf documents page labels as a number tree with sorted integer `/Nums` keys. A malformed non-key token should not make later valid key/value pairs disappear from native WordPress page metadata. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelNumsEntriesFromArray()` now resynchronizes after malformed `/Nums` key tokens by advancing one array token until the next valid page-index key, instead of consuming the next integer as the malformed key's paired value.
- `MarkerAppPreview::pageLabelSectionsFromNums()` mirrors the same fallback parser behavior so preview/page-image metadata stays aligned if the preview path has to parse PageLabels directly.
- Added a focused four-page fixture where `/Nums [(stray-key-noise) 0 Cover 1 Body 2 App 3 End]` must produce `Cover-`, `Body 4`, `App-Z`, and `End 9`.
- Added a WordPress smoke that emits Gutenberg page-break metadata with the recovered labels and proves fallback page numbers and malformed key noise are excluded.

## Evidence

Red-first focused run on the accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedKeyResyncBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resynchronizes PageLabels Nums after malformed key tokens before WordPress page metadata
Expected: ["Cover-","Body 4","App-Z","End 9"]
Actual: ["1","2","3","4"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedKeyResyncBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resynchronizes PageLabels Nums after malformed key tokens before WordPress page metadata
1 test files, 16 assertions, 0 failures
```

Adjacent PageLabels and preview regression run:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*Test\.php$' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 26 selected test files (root lock skipped)
26 test files, 658 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-key-resync-currentbase.php
```

The smoke exits 0 and emits `page_labels=["Cover-","Body 4","App-Z","End 9"]`, `preview_page_labels=["Cover-","Body 4","App-Z","End 9"]`, `selected_preview_page_label="End 9"`, `malformed_key_noise_skipped=true`, `fallback_page_numbers_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels behavior: +1 PASS case and +16 focused assertions.
- `phpPass`: `2858 -> 2859`.
- `wordpressScenarios`: `2395 -> 2396`.
- `mappedPdfPageLabelsMalformedKeyResyncCurrentBaseBehaviors`: `0 -> 1`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, malformed value ordering, duplicate malformed values, descending or out-of-range valid-key ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only resynchronizing after malformed non-key tokens inside a PageLabels `/Nums` array before later valid key/value pairs.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
