# markerPDF Page Tagged Content Reading Order Review

Slice: `page-tagged-content-reading-order-review-currentbase-20260602T1640Z`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py::naive_get_text` and `get_text_blocks`, which delegate PDF parsing/text extraction to pdftext/pypdfium before Marker converts blocks for downstream Markdown/WordPress use: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- PDFium's structure-tree page loader resolves a page `/StructParents` value through `/StructTreeRoot /ParentTree`, then walks the parent array by MCID index for that page: <https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7421/core/fpdfdoc/cpdf_structtree.cpp>
- The native PHP boundary must therefore bind page-local MCIDs through `/ParentTree` when StructElem leaves omit `/Pg`, while preserving existing `/RoleMap`, `/ActualText`/`/Alt`, and unlisted artifact exclusion behavior.

## Implementation

- `PdfTextExtractor` now parses `/StructTreeRoot /ParentTree` number trees, including indirect `/Kids` and `/Nums` array values.
- Page `/StructParents` keys now map to parent arrays whose indexes are MCIDs; the extractor merges those page-local MCID entries into the existing StructTree replay path without overriding already recoverable `/StructTreeRoot /K` order.
- `extractTaggedContent()` now emits page-local tagged rows for this ParentTree case with `page_number`, `mcid`, raw role, RoleMap-resolved role, content tags, and text.
- The WordPress smoke emits H2/paragraph Gutenberg blocks from the tagged rows and proves unlisted `/Artifact` MCIDs are suppressed.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL uses page StructParents ParentTree arrays for tagged-content reading-order review
Actual: Page one body second, Page one heading first, Page one artifact noise, ...
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 590 assertions, 0 failures
```

The example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-structparents-parenttree-reading-order-currentbase.php
parent_tree_order=true
page_local_mcid_binding=true
rolemap_resolved=true
excluded_unlisted_artifacts=true
```

Changed PHP lint passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-structparents-parenttree-reading-order-currentbase.php
```

Whitespace gate:

```text
git diff --check -- lanes/markerpdf
```

passed.

## Non-Overlap

This does not repeat the accepted StructTreeRoot MCID order, RoleMap tagged-content, marked-content ActualText/Alt, catalog Threads bead order, page-tree resource, or annotation appearance slices. The new behavior is specifically page `/StructParents` -> StructTreeRoot `/ParentTree` number-tree binding when tagged content is recoverable but leaf StructElem dictionaries omit `/Pg`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object inventory, dictionary/array parser, number-tree traversal, page tree walker, content-token parser, font maps, marked-content property parser, and existing WordPress smoke path. Full upstream parity remains gated on pdftext, pypdfium2/PDFium runtime execution, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI paths, and benchmark tooling.
