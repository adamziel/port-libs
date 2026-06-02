# outline-article-thread-bead-navigation-review-currentbase-20260602T1629Z

## Source Truth

- Upstream markerPDF pinned source: `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks and page metadata as the extraction boundary while navigation dictionaries stay outside visible text output: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF source-truth behavior for this slice is catalog `/Threads` with article thread `/F` first-bead references and bead dictionaries linked by `/N` and `/V`, page-bound by `/P`, and rectangle-bound by `/R`.
- Existing markerPDF lane behavior already keeps article beads out of visible text extraction; this slice makes the same navigation structure reviewable from outline and open-action targets.

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now emits `article_threads` review metadata for catalog `/Threads`, including thread title, bead count, bead object ids, page labels, page numbers, normalized rectangles, and previous/next bead references.
- Outline rows, outline action review rows, OpenAction review rows, and OpenAction destination rows now receive `target_article_beads` and `target_article_thread_titles` when their local page target has article beads.
- The WordPress smoke emits review metadata while confirming thread navigation dictionaries do not become body text.

## Verification

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-article-thread-bead-navigation-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php` passed: `1 test files, 34 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: `3 test files, 889 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-article-thread-bead-navigation-review-currentbase.php` passed and emitted `article_thread_bead_count=3`, `outline_target_beads=[22,23]`, `open_action_target_beads=[21]`, and `visible_text_excludes_thread_navigation=true`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: `83 test files, 5261 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `558 -> 560`.
- Mapped markerPDF semantics move `399 -> 400 / 78`.
- No root harness run: isolated markerPDF micro-slice only.

## Non-Overlap

This does not repeat accepted outline named-destination review, destination Fit operand normalization, outline action chain review, destination action transition metadata, page transition/action metadata, StructTree thread precedence, or existing visible text `/Threads` bead ordering. The new behavior is attaching catalog article thread bead rows to review-only outline/OpenAction page navigation targets.

## Dependency Closure

No new support component is needed. The slice reuses the lane-native PDF object parser, page-tree index map, destination resolver, PageLabels resolver, and outline/OpenAction review metadata. Full upstream markerPDF parity remains gated by heavy Python/model/runtime dependencies including pdftext, pypdfium2, Surya/OCR, PIL rendering, Streamlit/FastAPI execution, and model downloads, none of which were run for this bounded PHP slice.
