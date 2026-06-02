# Outline Named Destination Action Thread Review Current Base

Micro-slice: `outline-named-destination-action-thread-review-currentbase-20260602T171314Z`

Base accepted HEAD: `49180e79432b8b918699ff28f84476d5fe362bc7`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark resolution to the PDF engine and preserves title, level, and page index as navigation metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks separate from TOC/navigation metadata.
- PDF outline `/Dest` values may resolve through `/Names /Dests` to action dictionaries with local `/D` targets and chained `/Next` followups. Catalog `/Threads` article beads are navigation structure, not visible page text. This slice keeps those action/thread structures review-only for WordPress import.

## Red Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php`

failed in the named-destination action thread fixture:

- Expected outer outline destination/action key: `ArticleAction`.
- Actual resolved destination metadata: `ArticleStory`.
- The action chain also had no `destination_action_name` or `destination_action_target_article_beads` context for chained review rows.

Result: `1 test files, 12 assertions, 1 failures`.

## Implementation

- `PdfOutlineExtractor::destinationViewDetails()` now preserves the first named destination key while resolving nested named `/D` targets.
- `PdfOutlineExtractor::outlineActionReviewRows()` now annotates action rows reached through outline `/Dest` action dictionaries with:
  - `destination_action_name`
  - `destination_action_target_page`
  - `destination_action_target_page_label`
  - `destination_action_target_article_beads`
  - `destination_action_target_article_thread_titles`
- Local GoTo rows keep normal target page/thread metadata, and chained URI/JavaScript rows inherit only the named action target context. All rows remain `executes_on_import=false`.
- Added `wordpress-pdf-outline-named-destination-action-thread-review-currentbase.php` to prove the WordPress path keeps named destination keys, URI operands, JavaScript payloads, and article thread dictionaries out of visible Gutenberg paragraphs.

## Verification

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-action-thread-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php` passed: `1 test files, 30 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `5 test files, 416 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-action-thread-review-currentbase.php` passed and emitted `outline_destination=ArticleAction`, `outline_action_count=3`, `outline_action_destination_names=[ArticleAction,ArticleAction,ArticleAction]`, `chained_action_target_article_beads=[21,22]`, `article_thread_titles=[Named Action Article Thread]`, `visible_text_excludes_named_action_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- PHP behavior tests move `583 -> 585` because the focused file adds two TestRunner PASS cases.
- Mapped markerPDF/PDF semantics move `420 -> 421 / 78`.

## Non-Overlap

This does not repeat accepted plain outline named-destination resolution, Fit operand normalization, outline action chain review, remote destination action review, page transition/action metadata, article thread bead navigation metadata, OpenAction chain review, or ColorKey image mask work. The bounded behavior is the combined edge where an outline named destination resolves to a local action dictionary, that action targets another named destination on an article-thread page, and chained action rows need review-only target context without executing actions or leaking operands into visible text.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline walker, destination/name-tree resolver, action review walker, PageLabels resolver, article thread bead metadata, and visible text extraction boundary. Full upstream markerPDF runner parity remains gated by heavy Python/model/runtime dependencies including pdftext, pypdfium2, Surya/OCR, PIL rendering, Streamlit/FastAPI execution, and model downloads, none of which were run for this bounded PHP slice.
