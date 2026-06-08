# markerPDF PageLabels Nums Leaf Kids Boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T061651Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF pages before model/OCR stages; native PHP keeps `/PageLabels` as page-break and preview metadata rather than visible paragraph text.
- PDF `/PageLabels` is a number tree. A node with usable `/Nums` is a leaf; sibling `/Kids` on the same unbounded node must not backfill disjoint stale labels into WordPress page metadata.
- Existing current-base repair for malformed bounded intermediate nodes is preserved: a node with local `/Limits` can still merge direct `/Nums` repair rows with bounded `/Kids` rows.

## Implementation

- `PdfTextExtractor` now stops same-node `/Kids` traversal after usable `/Nums` entries on an unbounded PageLabels node.
- `MarkerAppPreview` applies the same boundary so preview page labels and image-plan metadata stay aligned with extraction.
- Added `PdfPageLabelsNumsLeafKidsBoundaryCurrentBaseTest.php` with a three-page fixture where a root `/Nums` section labels all pages and a stale same-node `/Kids` branch tries to relabel page 3.
- Added `wordpress-pdf-page-labels-nums-leaf-kids-boundary-currentbase.php` showing the WordPress page-break metadata path without Python, OCR, models, raster rendering, or external PDF tools.

## Red-First Evidence

Before the implementation change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNumsLeafKidsBoundaryCurrentBaseTest.php
```

Failed with labels `["Leaf-4","Leaf-5","stale-kid-99"]` instead of `["Leaf-4","Leaf-5","Leaf-6"]`.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNumsLeafKidsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 8 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 256 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-labels-nums-leaf-kids-boundary-currentbase.php
```

Result: exits 0 and emits `page_labels=["Leaf-4","Leaf-5","Leaf-6"]`, `selected_preview_page_label="Leaf-6"`, `nums_leaf_kids_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local `/Limits`, malformed `/Limits`, indirect operands, scalar/comment boundaries, escaped names, PDFDocEncoding prefixes, generation-exact dictionaries, missing-generation fallback, object-stream labels, duplicate `/Nums` keys, out-of-order kids, top-level private-key exclusion, malformed bounded intermediate node repair, or prefix/style/dictionary/array scalar tail rejection. The bounded behavior is only unbounded PageLabels `/Nums` leaf termination before disjoint stale same-node `/Kids`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
