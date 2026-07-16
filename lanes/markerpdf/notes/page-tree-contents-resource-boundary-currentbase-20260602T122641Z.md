## Page Tree Contents Resource Boundary

Slice: `page-tree-contents-resource-boundary-currentbase-20260602T122641Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/extract_text.py::naive_get_text`, iterates `range(len(doc))`, opens each PDFium page, asks that page for its textpage, and appends bounded page text. Source: https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Native PHP therefore treats discovered page leaves as authoritative. `/Contents` is a page dictionary value, not an inheritable page-tree value, while `/Resources` can be inherited through `/Pages` ancestors.

## Behavior

- `PdfTextExtractor` no longer falls back to whole-file decoded stream scanning when a page tree exists but no page leaf resolves a page `/Contents` stream.
- Page `/Contents` lookup now scans top-level page dictionary entries, so inline annotation dictionaries with their own `/Contents` keys inside `/Annots` cannot hide the real page content stream.
- Inherited `/Resources` still flow from `/Pages` ancestors to child page content streams, preserving page-local font decoding without inheriting ancestor `/Contents`.

## Red-First Probe

Current base before the fix leaked non-page streams for a page tree whose only page leaf had no `/Contents`:

```text
array (
  0 => 'Parent Contents Leak',
  1 => 'Orphan Stream Leak',
)
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 549 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
2 test files, 875 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-tree-contents-resource-boundary.php
emits Child Page Uses Inherited Resources with page_tree_without_leaf_contents_blocks_fallback_stream_scan=true, parent_pages_contents_not_inherited=true, orphan_stream_text_excluded=true, inherited_resources_preserved=true, executes_python_or_models=false, and executes_external_pdf_tools=false

php tools/run-tests.php lanes/markerpdf/tests
65 test files, 3903 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, top-level dictionary scanner, page-tree walker, stream decoder, inherited resource maps, ToUnicode font maps, and WordPress example renderer. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, live benchmark/runtime tooling, and external rendering/OCR tools.
