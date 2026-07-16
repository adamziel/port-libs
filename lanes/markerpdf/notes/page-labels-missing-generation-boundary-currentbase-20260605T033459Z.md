# markerPDF PageLabels missing-generation boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T033459Z`

## Source Truth

- Upstream markerPDF gets searchable PDF text and page-local metadata from the loaded PDF document before conversion; native PHP `/PageLabels` stay page-break and preview metadata aligned with those page boundaries.
- PDF indirect references include both object number and generation. A PageLabels value referenced as `30 0 R` must not bind to `30 1 obj` when generation 0 is missing, because that would let stale same-object-number label dictionaries relabel current pages.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview::objectBodyForReference()` is now generation-strict for indirect values, returning only the exact direct object body for the requested generation.
- Added a focused PageLabels fixture where `/Nums [0 30 0 R ...]` points at a missing generation while `30 1 obj` carries a stale label dictionary.
- Text extraction already fell back correctly; preview summary and selected image-plan metadata now match it by preserving labels `1` and `Body 2`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for the accepted labels while proving stale same-object-number generation labels remain excluded.

## Evidence

Red-first probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["1","Body 2"]
MarkerAppPreview::pageLabels(...) => ["stale-missing-generation-99","Body 2"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects missing-generation PageLabels references before preview metadata fallback
1 test files, 93 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-missing-generation-currentbase.php
```

The smoke emits `page_labels=["1","Body 2"]`, `preview_page_labels=["1","Body 2"]`, `selected_preview_page_label="Body 2"`, `missing_generation_fallback_preserved=true`, `stale_same_object_generation_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1360 -> 1361`
- `wordpressScenarios`: `1304 -> 1305`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, inherited/local `/Limits`, indirect `/Limits` operands, indirect `/S` `/P` `/St` label operands, indirect `/Nums` key or array resolution, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact present value dictionaries, top-level `/Kids` and `/Nums` token parsing, trailer `/Root` catalog selection, viewer preferences, outline page-label propagation, page transition/action review, or Type3/image/font/xref/parser slices. The bounded behavior is missing-generation indirect PageLabels references in marker-app preview metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
