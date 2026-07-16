# Outline OpenAction Thread PieceInfo Current Base

Micro-slice: `outline-openaction-thread-pieceinfo-currentbase-20260602T175420Z`

Base accepted HEAD: `1f51384b562639ecac3cfdac5c64ef58d0a7970f`

## Source Truth

- Upstream markerPDF pinned source: `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates outline/bookmark resolution to the PDF engine and exposes title, level, and page index as navigation metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks from `pdftext.dictionary_output()` separate from TOC/navigation metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF catalog `/OpenAction` entries can be destinations or action dictionaries. Destination name trees may point at action dictionaries with `/Next` followups; page `/PieceInfo`, page `/AF`, and catalog `/Threads` are review/navigation structures, not visible page text.

## Implementation

- `PdfOutlineExtractor::getOpenActionReviewActions()` now resolves catalog `/OpenAction` values through the destination map when the named destination points at an action dictionary.
- Such OpenAction action dictionaries emit all bounded `/Next` review rows and carry `destination_action_name` for the outer name-tree key.
- `PdfOutlineExtractor::getNavigationReviewMetadata()` now propagates the resolved OpenAction target page context to chained rows through:
  - `destination_action_target_page`
  - `destination_action_target_page_label`
  - `destination_action_target_article_beads`
  - `destination_action_target_article_thread_titles`
  - `destination_action_target_page_review`
- Added a focused test fixture where `/OpenAction /ArticleOpen` resolves through `/Names /Dests` to a `/GoTo` action with URI and JavaScript `/Next` actions, targeting a page with `/PieceInfo`, an associated XML source file, and article-thread beads.
- Added `wordpress-pdf-outline-openaction-thread-pieceinfo-currentbase.php` to prove the WordPress path keeps review payloads/actions out of visible Gutenberg paragraphs.

## Verification

- Red-first:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php`
  failed before the source change: `1 test files, 16 assertions, 1 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php`
  passed: `1 test files, 36 assertions, 0 failures`.
- Related outline/page-review gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php`
  passed: `7 test files, 729 assertions, 0 failures`.
- PHP lint passed for:
  `lanes/markerpdf/src/PdfOutlineExtractor.php`,
  `lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php`, and
  `lanes/markerpdf/examples/wordpress-pdf-outline-openaction-thread-pieceinfo-currentbase.php`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-openaction-thread-pieceinfo-currentbase.php`
  passed and emitted `open_action_destination_names=["ArticleOpen","ArticleOpen","ArticleOpen"]`, `open_action_types=["GoTo","URI","JavaScript"]`, `chained_action_count=2`, `target_pieceinfo_review_state=openaction-thread-pieceinfo`, `target_attachment_checksum_matches=true`, `target_article_beads=[21,22]`, `visible_text_excludes_review_metadata=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests move `617 -> 619` PASS cases.
- Mapped markerPDF/PDF semantics move `450 -> 451 / 78`.
- Root harness: not run - isolated markerPDF micro-slice.

## Non-Overlap

This does not repeat accepted plain catalog OpenAction safety review, catalog OpenAction `/Next` direct-action review, indirect name-tree destination parsing, outline named-destination action target context, page `/PieceInfo` extraction, associated-file checksum extraction, article-thread bead navigation, or outline/page target page-review enrichment. The new behavior is specifically catalog `/OpenAction` names whose destination-map value is an action dictionary with chained review rows that need the named action target's page review and article-thread context.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, destination/name-tree resolver, action review walker, PageLabels resolver, `PdfPagePropertyExtractor`, article-thread metadata, associated-file checksum review, and native visible-text extractor. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
