# PageLabels Deep Kids Depth Boundary Current Base

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T153944Z`

Accepted base: `514053c7d86aad395662bad8b28dd55f8e398a73`

## Source Truth

- Upstream markerPDF keeps searchable-PDF import centered on page iteration and extracted text metadata, with model/OCR behavior intentionally outside this no-GPU slice.
- The relevant PDF parser source truth is bounded PageLabels number-tree traversal. pypdf caps page-label number-tree descent at 100 levels before returning no labels from that branch, preventing pathological `/Kids` chains from driving page labels.
- PDFium page-label tests cover nested number-tree traversal and establish that valid nested label sections remain ordinary metadata when the tree is bounded and well formed.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now stops recursive `/Kids` traversal once the PageLabels number-tree depth reaches 100.
- `MarkerAppPreview::pageLabelSections()` applies the same 100-level cap to the fallback preview parser.
- The cap is fail-closed for the current branch only. A stale 105-level `/Kids` chain is ignored, while a later shallow valid catalog `/PageLabels` dictionary still supplies page labels for extracted text, WordPress page-break metadata, and preview image plans.

## Evidence

- Focused red/green target: `lanes/markerpdf/tests/PdfPageLabelsDeepKidsDepthBoundaryCurrentBaseTest.php`
- Adds 2 focused PASS cases and 27 assertions.
- Adds WordPress smoke: `lanes/markerpdf/examples/wordpress-pdf-page-labels-deep-kids-depth-currentbase.php`
- Expected smoke markers:
  - `too_deep_kids_chain_rejected=true`
  - `later_shallow_page_labels_preserved=true`
  - `executes_python_or_models=false`
  - `executes_external_pdf_tools=false`

## Non-Overlap

This slice does not duplicate the accepted PageLabels work around direct/indirect labels, duplicate keys, limits filtering, generated suffix bounds, escaped catalog names, UTF-8/UTF-16 prefixes, xref-stream repair, trailer-root selection, or supplied-boundary layout/order artifacts. It only covers acyclic too-deep `/Kids` traversal in the native PageLabels number-tree path.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object scanner, exact-generation resolver, PageLabels parsers, text extraction handoff, marker app preview summary, and WordPress smoke path. No Python, CUDA, OCR, Surya, Texify, Torch/model batching, raster rendering, JavaScript, PDFium process, or external PDF tool is required.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` -> no syntax errors.
- `php -l lanes/markerpdf/src/MarkerAppPreview.php` -> no syntax errors.
- `php -l lanes/markerpdf/tests/PdfPageLabelsDeepKidsDepthBoundaryCurrentBaseTest.php` -> no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-deep-kids-depth-currentbase.php` -> no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDeepKidsDepthBoundaryCurrentBaseTest.php` -> 1 test file, 27 assertions, 0 failures.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*CurrentBaseTest\.php$|/CorePdfConverterPageLabelsBoundaryCurrentBaseTest\.php$|/MarkerAppPreviewTest\.php$' | sort)` -> 46 test files, 938 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-labels-deep-kids-depth-currentbase.php` -> exits 0; smoke markers confirm too-deep chain rejection, later shallow labels preserved, no Python/models, and no external PDF tools.
- `git diff --check -- lanes/markerpdf` -> no whitespace errors.

Root harness: not run - isolated micro-slice.
