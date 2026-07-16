# Page Label Named Destination StructElem Review

## Source Truth

- Upstream markerPDF delegates PDF page text extraction to the pdftext/PDFium boundary in `marker/pdf/extract_text.py` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`: https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium structure-tree loading resolves page `/StructParents` through `/StructTreeRoot /ParentTree` for page-local marked-content parents: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7421/core/fpdfdoc/cpdf_structtree.cpp
- The native PHP boundary keeps PDF catalog `/PageLabels` and `/Names /Dests` navigation metadata aligned with page-local StructElem review rows, without executing PDF actions, Python, pdftext, pypdfium/PDFium, Poppler, Ghostscript, or models.

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now indexes `PdfTextExtractor::extractTaggedContent()` rows by page index and attaches them to local outline, outline-action, catalog OpenAction, and OpenAction destination targets.
- Target rows now include `target_tagged_content` and stable `target_structure_roles` when a named destination lands on a page with recoverable StructTree/ParentTree MCID rows.
- The metadata source list includes `tagged_content` only when at least one navigation target actually carries tagged-content rows.

## Evidence

Red-first focused check before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
FAIL attaches StructElem tagged content to page-label named-destination navigation review
Expected source included tagged_content; actual source only outline and open_action.
1 test files, 278 assertions, 1 failures
```

Focused check after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
1 test files, 299 assertions, 0 failures
```

## Non-Overlap

This does not repeat standalone `/PageLabels` extraction, standalone `/Names /Dests` TOC resolution, standalone destination Fit operand normalization, standalone StructTreeRoot RoleMap extraction, page `/StructParents` ParentTree reading order, page transition/action review, article-thread navigation, or OpenAction action-chain review. The new behavior is the composition boundary that carries page-label plus named-destination target context together with StructElem tagged rows for WordPress navigation review.

## Dependency Closure

No new support component is needed. The slice reuses the lane-native PDF object parser, page-label number-tree resolver, named-destination resolver, action classifier, and StructTree/ParentTree tagged-content extractor. Full upstream runner parity remains gated by heavy Python/model/runtime dependencies, including pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, Streamlit/FastAPI, and model downloads, none of which were run.
