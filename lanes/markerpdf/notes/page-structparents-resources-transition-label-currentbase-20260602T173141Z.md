# markerPDF page StructParents resources transition label current-base slice

Micro-slice: `page-structparents-resources-transition-label-currentbase-20260602T173141Z`

Accepted base: `f6a226052136abadc56f7b8d8b89c4b84d502d1b`

## Source Truth

- Upstream markerPDF source truth remains `sddai/markerPDF` / marker at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, where PDF text extraction is delegated at the `marker/pdf/extract_text.py` boundary to pdftext/PDFium-style page extraction before Marker converts page blocks to Markdown.
- Relevant PDF parser behavior: page dictionaries carry `/StructParents`, `/Resources`, `/Dur`, and `/Trans`; the catalog page-label number tree supplies current page labels; `/Resources` is inherited down the page tree unless a leaf supplies a local dictionary; `/StructParents` indexes the structure parent tree arrays for MCID reading order.

## Behavior Added

- Added `PdfPagePropertyExtractor::extractPageBoundaryMetadata()`.
- The new native review rows expose:
  - current `page_label` values from catalog `/PageLabels`;
  - page `/StructParents` keys and resolved `/StructTreeRoot /ParentTree` MCID rows with RoleMap roles;
  - effective page-tree `/Resources`, including leaf-vs-inherited ownership, resource object, categories, font aliases, XObject aliases, property aliases, and color-space aliases;
  - page presentation metadata from existing `/Dur` and `/Trans` extraction.
- Added a WordPress smoke that renders only visible Gutenberg paragraphs while keeping page labels, transitions, resource property text, and StructTree review strings as metadata.

## Red-First Evidence

Before the source change, the new focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php
FAIL reviews page StructParents resources transitions and current labels without changing visible text
Call to undefined method PortLibs\MarkerPDF\PdfPagePropertyExtractor::extractPageBoundaryMetadata()
1 test files, 0 assertions, 1 failures
```

## Verification

- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-structparents-resources-transition-label-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php` passed: `1 test files, 50 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `4 test files, 1114 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-page-structparents-resources-transition-label-currentbase.php` passed and emitted `page_labels=["deck-7","appendix-2"]`, `struct_parent_keys=[4,5]`, `parent_tree_mcids=[[0,1],[0,2]]`, `resource_inheritance=[false,true]`, `transition_styles=["Split","Dissolve"]`, and `visible_text_excludes_review_metadata=true`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused behavior tests move `604 -> 605 pass / 0 fail`.
- Mapped markerPDF semantics move `438 -> 439 / 78`.
- Added one WordPress smoke scenario for page-boundary review metadata.

## Non-Overlap

This does not repeat accepted page `/StructParents` visible text reading-order extraction, page `/AF`/PieceInfo review, page transition/action review, PageLabels number-tree parsing, or page resource inheritance text decoding. It composes those already accepted primitives into a new page-boundary review API that reports resource ownership and StructParents/ParentTree metadata for import UIs while visible text extraction remains unchanged.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object parsing, page-tree traversal, PageLabels parsing, StructTree ParentTree parsing, RoleMap handling, existing transition review extraction, and text extraction. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were run for this bounded PHP slice.
