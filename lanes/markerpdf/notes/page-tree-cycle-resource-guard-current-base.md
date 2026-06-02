# Page Tree Cycle Resource Guard

## Source Truth

- Upstream source: `sddai/markerPDF` pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream boundary: `marker/pdf/extract_text.py::naive_get_text` and `get_text_blocks` receive bounded text per page from pdftext/pypdfium, while `marker_app.py::open_pdf` / `page_count` report PDFium page inventory. The native PHP boundary should therefore enumerate reachable page leaves once, preserve valid inherited page-tree resources, and avoid whole-file stream fallback for malformed cyclic page trees.

## Behavior

`PdfTextExtractor` now de-duplicates page leaves reached through cyclic or repeated `/Kids` references before text, page-label, and outline-page extraction. Its inherited resource walk now follows `/Parent` links only through `/Type /Pages` ancestors, so malformed page-to-page parent chains cannot contribute unrelated page resources.

`MarkerAppPreview` now applies the same de-duplicated page-leaf boundary before `page_count` and preview inventory metadata. Its fallback parent geometry walk also stops at non-`/Pages` parents.

The WordPress smoke `examples/wordpress-pdf-page-tree-cycle-resource-guard-import.php` emits two Gutenberg paragraphs, `Cycle Resource First` and `Cycle Resource Second`, with native-only flags and page count `2`, while excluding the orphan fallback stream.

## Evidence

Red-first probe on current base showed duplicate page extraction before the fix:

```text
array (
  0 => 'Cycle Page',
  1 => 'Cycle Page',
)
array (
  0 => '1',
  1 => '2',
)
2
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 382 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
1 test files, 69 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-tree-cycle-resource-guard-import.php
emits Cycle Resource First, Cycle Resource Second, derived_page_count_from_reachable_leaves=2, duplicate_page_leaves_blocked=true, orphan_fallback_stream_excluded=true

php tools/run-tests.php lanes/markerpdf/tests
58 test files, 2404 assertions, 0 failures

php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/tests/MarkerAppPreviewTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-tree-cycle-resource-guard-import.php
all changed PHP files reported no syntax errors

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'
both markerPDF JSON metadata files validated

git diff --check -- lanes/markerpdf
passed
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page-tree walker, inherited resource lookup, CMap/font maps, content stream parser, and marker-app preview geometry planner without Python, pdftext, pypdfium, PIL, Poppler, Ghostscript, or model downloads.

Full upstream Python/model/benchmark parity remains dependency-gated for the same reasons recorded in `lane-status.json`.
