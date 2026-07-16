# Outline Named Destination Action Page Review Current Base

Micro-slice: `outline-thread-page-review-conflict-rebase-currentbase-20260602T173108Z`

Base accepted HEAD: `f6a226052136abadc56f7b8d8b89c4b84d502d1b`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` keeps page text blocks and TOC/navigation metadata separate by returning page blocks from `pdftext.dictionary_output()` plus TOC metadata from `get_pdf_toc`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/schema/page.py` models page-local blocks, page number, rotation, and character blocks separately from document-level navigation/review dictionaries: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/schema/page.py
- PDF source-truth behavior for this bounded edge: outline `/Dest` values can resolve through `/Names /Dests` to action dictionaries, those action dictionaries can target a local `/D` page and carry `/Next` followups, and the target page can carry `/PieceInfo` plus page `/AF` Filespec review metadata. WordPress import must keep all of those action/page dictionaries review-only.

## Red Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php`

failed in the new page-review chained-action assertion:

- Expected: `action-thread-review`
- Actual: `NULL`
- Result: `1 test files, 36 assertions, 1 failures`

The local GoTo row already had `target_page_review`; chained URI/JavaScript rows inherited named action target page/thread labels but not the named action target page-review payload.

## Implementation

- `PdfOutlineExtractor::destinationActionTargetContext()` now copies `target_page_review` into `destination_action_target_page_review`.
- The focused named-destination action/thread fixture now gives the target page `/PieceInfo` and a page-associated Filespec with checksum metadata.
- Chained URI and JavaScript action review rows now inherit target page PieceInfo and associated-file review metadata while remaining `executes_on_import=false`.
- The WordPress smoke now emits `chained_action_target_page_review_state=action-thread-review` and `chained_action_target_attachment=article-review.xml`, while visible page paragraphs exclude destination names, URI/JavaScript operands, thread titles, PieceInfo values, and embedded review payload text.

## Verification

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-action-thread-review-currentbase.php` passed.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php` passed: `1 test files, 40 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlinePagePieceInfoTransitionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed: `6 test files, 633 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-action-thread-review-currentbase.php` passed and emitted `chained_action_target_page_review_state="action-thread-review"`, `chained_action_target_attachment="article-review.xml"`, `outline_action_destination_names=["ArticleAction","ArticleAction","ArticleAction"]`, `chained_action_target_article_beads=[21,22]`, and `visible_text_excludes_named_action_operands=true`.

## Status Delta

- Behavior tests move `604 -> 605`.
- Mapped markerPDF/PDF semantics move `438 -> 439 / 78`.
- Root harness: not run - isolated markerPDF micro-slice.

## Non-Overlap

This does not repeat accepted plain outline named-destination resolution, article-thread bead navigation, standalone page PieceInfo extraction, page associated-file checksum extraction, or outline/OpenAction target page-review enrichment. The new behavior is limited to named destination action dictionaries: chained action rows inherit the local action target page-review payload through `destination_action_target_page_review`.

## Dependency Closure

No new support component is needed. This reuses the lane-native PDF object parser, name-tree destination resolver, action review walker, page review extractor, embedded-file checksum review metadata, article-thread metadata, and visible text extraction boundary. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were run for this bounded PHP slice.
