# markerPDF PageLabels top-level key boundary

## Source truth

- Upstream markerPDF routes searchable PDF text through page-local PDF parser boundaries before model/OCR stages; native PHP keeps `/PageLabels` as page-break and preview metadata, not visible paragraph text.
- PDF page labels are stored in the catalog `/PageLabels` number tree. Number-tree `/Nums` arrays contain sorted integer keys and label dictionaries; when a number-tree node has `/Nums`, that leaf node supplies labels directly. This mirrors pypdf's page-label helper, which reads a node's `/Nums` before considering `/Kids`: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- Page-label node keys (`/Nums`, `/Kids`, `/Limits`) and label dictionary keys (`/S`, `/P`, `/St`) are top-level dictionary entries. Nested private review dictionaries must not override WordPress page-break metadata.

## Implementation

- `PdfTextExtractor` now uses a PageLabels-specific top-level dictionary lookup for `/Nums`, `/Kids`, `/Limits`, `/S`, `/P`, and `/St`.
- A PageLabels node with top-level `/Nums` is treated as a leaf; stale sibling `/Kids` branches are ignored for that node.
- `MarkerAppPreview` applies the same `/Nums` leaf rule in its fallback PageLabels parser, keeping preview/image-plan metadata aligned.

## Red-first evidence

Before the fix, a fixture with `/PageLabels << /Private << /Nums [...] >> /Nums [...] >>` returned:

```text
["nested-root-99", "nested-root-100", "nested-root-101"]
```

The expected labels are `Top-4`, `Body-8`, and `Body-9`; nested private `/Nums`, nested private `/P`/`/S`/`/St`, and stale sibling `/Kids` rows must stay excluded.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 160 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-top-level-keys-currentbase.php
exit 0; emits page_labels ["Top-4","Body-8","Body-9"], nested_root_nums_excluded=true, nested_label_operands_excluded=true, stale_kids_after_nums_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

Root harness not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted PageLabels inherited `/Limits`, indirect `/Limits`, indirect `/Nums` array, indirect key operands, indirect `/S` `/P` `/St` operands, escaped names, PDFDocEncoding prefixes, generation-exact dictionaries, missing-generation fallback, object-stream labels, token-boundary root/kid references, comment-delimited references, malformed `/Limits`, trailer-root selection, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is top-level PageLabels dictionary key selection and `/Nums` leaf precedence before nested private decoys.

## Dependency closure

No new support component is needed. This slice reuses the native PDF object scanner, top-level dictionary parser, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.
