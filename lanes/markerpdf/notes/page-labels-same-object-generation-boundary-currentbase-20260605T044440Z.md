# markerPDF PageLabels same-object generation boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T044440Z`

## Source Truth

- Upstream markerPDF gets searchable PDF text and page-local metadata from loaded PDF page boundaries; native PHP `/PageLabels` stay page-break and preview metadata aligned with those physical pages.
- PDF indirect references include object number and generation. A valid PageLabels value can resolve from `30 0 R` to an explicit `30 1 R`; that is different from accepting `30 1 obj` when generation `0` is missing.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview` now tracks PageLabels indirect reference cycles by `object:generation` keys instead of blocking all later references with the same object number.
- This keeps preview metadata aligned with `PdfTextExtractor` for exact-generation chains like `/Nums [0 30 0 R]`, `30 0 obj 30 1 R`, `30 1 obj << /P (Front ) /S /r /St 4 >>`.
- Missing-generation references still fail closed because no object body is returned unless the requested generation exists.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Body 8`, and `Body 9` while proving numeric fallback and stale sibling-generation labels stay excluded.

## Evidence

Red-first probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["Front iv","Body 8"]
MarkerAppPreview::pageLabels(...) => ["1","Body 8"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves same-object PageLabels generation chains before preview metadata fallback
1 test files, 107 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-same-object-generation-currentbase.php
```

The smoke emits `page_labels=["Front iv","Body 8","Body 9"]`, `preview_page_labels=["Front iv","Body 8","Body 9"]`, `selected_preview_page_label="Body 9"`, `same_object_generation_chain_imported=true`, `numeric_fallback_rejected=true`, `stale_sibling_generation_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `100 -> 107`
- `phpPass`: `1423 -> 1424`
- `wordpressScenarios`: `1352 -> 1353`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, inherited/local `/Limits`, indirect `/Limits` operands, indirect `/S` `/P` `/St` label operands, indirect `/Nums` key or array resolution, escaped catalog names, PDFDocEncoding prefixes, UTF-16 prefix decoding, alphabetic repeated-letter formatting, generation-exact present value dictionaries, missing-generation fallback, top-level `/Kids` and `/Nums` token parsing, trailer `/Root` catalog selection, viewer preferences, outline page-label propagation, page transition/action review, or Type3/image/font/xref/parser slices. The bounded behavior is only same-object-number, different-generation indirect PageLabels chains in marker-app preview metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
