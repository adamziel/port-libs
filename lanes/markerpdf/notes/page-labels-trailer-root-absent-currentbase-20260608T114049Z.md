# markerPDF PageLabels trailer Root absent boundary

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` remains page-break and preview metadata under the current no-GPU scope. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. PDFium PageLabel coverage models labels as data hanging from the document catalog, not from arbitrary stale catalog-shaped objects. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- When the selected trailer `/Root` points to a current catalog with no `/PageLabels`, native extraction must fall back to physical page labels instead of scanning an older catalog object that still has stale labels.

## Implementation

- `PdfTextExtractor::pageLabelsDictionaryBodies()` now limits PageLabels discovery to the selected trailer-root catalog when the current trailer root was resolved.
- A new helper, `pageLabelDictionariesFromCatalogBody()`, keeps the old catalog-body parsing behavior reusable while preserving fallback catalog scanning only for PDFs without a selected trailer-root reference.
- Added a classic-xref fixture with stale object `1 0 obj` catalog PageLabels, selected trailer `/Root 7 0 R`, current pages `9 0 R` and `11 0 R`, and no PageLabels on the selected catalog.

## Evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTrailerRootAbsentBoundaryCurrentBaseTest.php
FAIL uses default PageLabels when current trailer Root omits PageLabels before stale catalog fallback
Expected: ["1","2"]
Actual: ["stale-root-99","stale-root-100"]
1 test files, 1 assertions, 1 failures
```

Focused regression after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTrailerRootAbsentBoundaryCurrentBaseTest.php
1 test files, 11 assertions, 0 failures
```

Broader PageLabels and marker preview family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*CurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
41 test files, 853 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-trailer-root-absent-currentbase.php
```

The smoke reports `current_root_default_labels_preserved=true`, `stale_catalog_labels_excluded=true`, `visible_text_uses_current_root_pages=true`, `preview_page_object_ids=[9,11]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `3072 -> 3073`
- Focused PageLabels assertions: new test adds `11` assertions
- WordPress scenarios: `2537 -> 2538`
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels/catalog-root behavior cluster.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels` precedence, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16 prefixes, malformed dictionary/array object tails, trailer `/Root` selection when that selected root has PageLabels, xref-stream root selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only the selected trailer-root catalog omitting `/PageLabels` while stale older catalog-shaped objects contain them.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, selected trailer-root catalog resolver, dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
