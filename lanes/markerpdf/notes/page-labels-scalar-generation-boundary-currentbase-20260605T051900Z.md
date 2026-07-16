# markerPDF PageLabels scalar generation boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T051900Z`

## Source Truth

- Upstream markerPDF gets searchable PDF text and page-local metadata from page iteration before conversion; native PHP `/PageLabels` remain page-break and preview metadata aligned with those page boundaries.
- PDF indirect references include object number and generation. Page-label number-tree scalar operands such as `/Limits`, `/S`, `/P`, and `/St` must resolve the referenced generation exactly, not a later same-object-number definition.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now resolves PageLabels indirect `/Limits`, `/S`, `/P`, and `/St` scalar operands through the generation-aware PageLabels object lookup.
- Added a focused fixture where `/P 30 0 R`, `/S 31 0 R`, and `/St 32 0 R` must select generation-zero scalar objects while higher-generation decoys are present.
- Added a focused fixture where `/Limits [30 0 R 31 0 R]` must select exact generation-zero bounds before rejecting a stale kid `/Nums` entry outside the current label range.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `1`, `Real-iv`, and `App-Z` while proving stale scalar and limit generation decoys stay excluded.

## Evidence

Red-first scalar operand probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["1","2"]
MarkerAppPreview::pageLabels(...) => ["Real-iv","Real-v"]
```

Red-first `/Limits` operand probe before implementation:

```text
PdfTextExtractor::extractPageLabels(...) => ["stale-front-9","Body 4","App-Z"]
MarkerAppPreview::pageLabels(...) => ["1","Body 4","App-Z"]
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps generation-exact PageLabels scalar operands aligned with preview metadata
PASS keeps generation-exact PageLabels Limits operands before stale kid labels
1 test files, 122 assertions, 0 failures
```

Adjacent PageLabels/text extraction sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 860 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-scalar-generation-currentbase.php
```

The smoke emits `page_labels=["1","Real-iv","App-Z"]`, `preview_page_labels=["1","Real-iv","App-Z"]`, `selected_preview_page_label="Real-iv"`, `scalar_generation_decoys_rejected=true`, `limits_generation_decoys_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1457 -> 1459`
- `wordpressScenarios`: `1375 -> 1376`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 7`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct or indirect `/Kids`, inherited/local `/Limits`, ordinary indirect `/Limits` operands without generation conflicts, indirect `/Nums` key or array resolution, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation references, trailer `/Root` catalog selection, viewer preferences, outline page-label propagation, page transition/action review, or XMP/metadata boundary work. The bounded behavior is generation-exact scalar PageLabels operand resolution in `PdfTextExtractor` and preview alignment.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
