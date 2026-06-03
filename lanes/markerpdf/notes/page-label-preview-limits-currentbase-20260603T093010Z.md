# markerPDF Page Label Preview Limits Current Base

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260603T093010Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses pypdfium/pdftext page iteration as the page boundary for searchable PDF imports and marker-app previews.
- PDF catalog `/PageLabels` is a number tree. A node `/Limits` array bounds which `/Nums` keys belong to that node, so stale or misplaced keys inside the same physical PDF object must not relabel page previews.
- This slice stays in native no-GPU scope: no OCR, Surya/Texify/Torch, pypdfium execution, Python, model workers, raster rendering, or external PDF tools were run.

## Behavior

- `MarkerAppPreview` now applies PageLabels number-tree `/Limits` before accepting `/Nums` entries.
- Preview page inventory and `getPageImagePlan()` now match `PdfTextExtractor` label boundaries, so a stale key such as page index `1` inside a node limited to `[2 2]` cannot override the page-image label used by WordPress preview metadata.
- The same walker now resolves indirect `/Kids` arrays before traversing child PageLabels nodes.

## Evidence

Red-first focused test before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
FAIL honors page label number tree limits before preview image page boundaries
Actual: ['front-ii', 'wrong-40', 'Body 7']
```

Focused page-label family after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 738 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-app-preview.php
```

The smoke emitted `page_labels=["front-ii","Body 7"]`, `selected_page_label="Body 7"`, `ignored_stale_page_label_key=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted text-extractor PageLabels `/Limits`, viewer-preference review metadata, page-box/UserUnit preview geometry, CropBox clipping, page-tree resource inheritance, named-destination metadata, or stream-filter boundary work. The new behavior is specifically marker-app preview/page-image metadata honoring PageLabels number-tree boundaries.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object parser and existing MarkerAppPreview value/array/dictionary helpers. Full upstream markerPDF Python/model benchmark parity remains intentionally out of scope under the current no-GPU directive.
