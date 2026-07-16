# markerPDF PageLabels trailer Root page fallback boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T184302Z`

Accepted base: `5fc1508fa8cbb6f73d200148dae4d18548fb8029`

## Source Truth

- Upstream markerPDF extracts searchable PDF text and preview metadata from the selected PDF document structure before OCR/model work; native PHP `/PageLabels` remains page-break and preview metadata under the current no-GPU scope. Source: `UPSTREAM_TEST_MANIFEST.json` pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- PDF `/PageLabels` is catalog metadata hanging from the selected trailer `/Root`, not from arbitrary older catalog-shaped objects. The accepted trailer-root PageLabels slices already apply that rule to native text extraction and normal preview inventory.
- This slice covers the remaining `MarkerAppPreview` fallback-only branch: if the selected trailer-root catalog has an unusable `/Pages` reference, direct `/Page` inventory may still be used for preview rows, but stale catalog objects must not replace the selected root for PageLabels.

## Implementation

- `MarkerAppPreview::pageInventory()` now scans alternate catalog objects only when no selected trailer-root catalog was resolved.
- When a selected root exists but its `/Pages` reference is broken, preview falls back to direct `/Page` objects while keeping the selected root catalog body for PageLabels.
- Added a fixture where `/Root 7 0 R` has current `/PageLabels 20 0 R` and missing `/Pages 99 0 R`, while stale catalog `1 0 obj` has usable `/Pages 2 0 R` and stale `/PageLabels 30 0 R`.

## Evidence

Red probe before the source edit:

```text
PdfTextExtractor::extractPageLabels($pdf) => []
MarkerAppPreview::openPdfSummary($pdf) page labels => ["stale-catalog-99","stale-app-Z"]
MarkerAppPreview::openPdfSummary($pdf) page object ids => [3,4]
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTrailerRootPageFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps selected trailer Root PageLabels when preview falls back to direct pages
1 test files, 10 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*CurrentBaseTest\.php$|/CorePdfConverterPageLabelsBoundaryCurrentBaseTest\.php$|/MarkerAppPreviewTest\.php$' | sort)
Focused test run: 50 selected test files (root lock skipped)
50 test files, 989 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-trailer-root-page-fallback-currentbase.php
```

The smoke exits 0 and reports `text_extractor_page_labels_unavailable=true`, `preview_page_labels=["Current-4","Now-Z"]`, `preview_page_object_ids=[3,4]`, `selected_root_labels_preserved=true`, `stale_catalog_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsTrailerRootPageFallbackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-trailer-root-page-fallback-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors. `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused behavior: +1 PASS case and +10 focused assertions.
- `phpPass`: `3397 -> 3398`.
- `wordpressScenarios`: `2765 -> 2766`.
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels/trailer-root and MarkerAppPreview fallback behavior cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, `/Type`, or catalog `/PageLabels` keys, malformed key/value ordering, duplicate malformed values, descending/out-of-range ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16/UTF-8 prefix handling, empty hex prefixes, malformed dictionary/array object tails, normal selected trailer `/Root` labels, selected trailer `/Root` with no `/PageLabels`, xref-stream root labels, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, or page resource generation behavior. The bounded behavior is only fallback preview inventory preserving selected trailer-root `/PageLabels` when the selected root's page tree is unusable and direct page-object fallback is used.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, selected trailer-root catalog resolver, direct page-object preview inventory, PageLabels fallback parser, focused PHP tests, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
