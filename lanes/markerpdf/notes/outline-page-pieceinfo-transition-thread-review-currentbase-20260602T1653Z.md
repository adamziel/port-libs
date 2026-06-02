# outline-page-pieceinfo-transition-thread-review-currentbase-20260602T1653Z

## Source Truth

- Upstream markerPDF pinned source: `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/extract_text.py::get_text_blocks` delegates page text blocks and TOC extraction to `pdftext.dictionary_output()` and pypdfium/page helpers, keeping PDF navigation dictionaries outside visible page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream markerPDF `marker/schema/page.py` models page objects as page-local block/text metadata, with page numbers, bbox, rotation, and character blocks separate from document-level navigation/review dictionaries: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- Relevant dependency/PDF parser boundary: pypdf constants classify page `/Trans`, page `/AA`, and page `/PieceInfo` as page dictionary entries, while `/Threads` and outline/open-action dictionaries remain separate navigation/catalog structures: https://pypdf.readthedocs.io/en/5.7.0/_modules/pypdf/constants.html

## Implementation

- `PdfOutlineExtractor::getNavigationReviewMetadata()` now composes `PdfPagePropertyExtractor::extractPageReviewMetadata()` rows into navigation review output.
- Outline rows, outline action review rows, catalog OpenAction review rows, and catalog OpenAction destination rows receive `target_page_review` when their local page target has `/PieceInfo`, page `/AF`, `MarkInfo`, or tagged `/UserProperties`.
- Existing target page transition/action metadata and article-thread bead metadata are preserved, so a single outline target can carry page label, `/Dur`, `/Trans`, page `/AA`, page PieceInfo, associated-file checksum state, tagged UserProperties, and article beads without executing actions or exposing review payloads as body text.
- Added a WordPress smoke for the composite import path.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php` failed before the source change because `page_review` and `target_page_review` were absent: `1 test files, 9 assertions, 1 failures`.
- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-page-pieceinfo-transition-thread-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php` passed: `1 test files, 49 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed: `5 test files, 532 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-page-pieceinfo-transition-thread-review-currentbase.php` passed and emitted `navigation_sources=["outline","outline_actions","open_action","page_presentations","article_threads","page_review"]`, `outline_target_pieceinfo_applications=["WPImport"]`, `outline_target_attachment_filenames=["deck-source.xml"]`, `outline_target_user_properties=["WP Block","Needs Manual Review"]`, `outline_target_article_threads=["Deck Article Thread"]`, and `visible_text_excludes_review_metadata=true`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `577 -> 579`.
- Mapped markerPDF semantics move `414 -> 415 / 78`.
- No root harness run: isolated markerPDF micro-slice only.

## Non-Overlap

This does not repeat standalone page `/PieceInfo` extraction, page associated-file checksum extraction, page transition/action metadata, outline destination action review, outline name-tree transition review, catalog OpenAction chaining, or standalone catalog article-thread bead navigation. The new behavior is the composition boundary: same-document outline/OpenAction targets now inherit page-review rows when they point at pages with review-only page metadata.

## Dependency Closure

No new support component is needed. The slice reuses the lane-native PDF object parser, PageLabels resolver, outline/OpenAction destination resolver, `PdfPagePropertyExtractor`, page presentation extraction, article-thread extraction, and content-stream text extractor. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were run for this bounded PHP slice.
