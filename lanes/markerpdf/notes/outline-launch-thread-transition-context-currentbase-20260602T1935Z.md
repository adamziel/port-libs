# Outline Launch Thread Transition Context Current Base

Micro-slice: `outline-launch-thread-transition-context-currentbase`

Base accepted HEAD: `2a344ae8c1b485daa88b3fe8a487f8ab30d2feff`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` calls `marker.cleaners.toc::get_pdf_toc`, keeps PDF TOC metadata in `out_meta["pdf_toc"]`, and keeps page text blocks separate from navigation metadata:
  - `https://raw.githubusercontent.com/sddai/markerPDF/master/marker/pdf/extract_text.py`
  - `https://raw.githubusercontent.com/sddai/markerPDF/master/marker/cleaners/toc.py`
- Relevant PDF parser behavior: outline `/A` action dictionaries can carry `/Next` action chains. `/Launch` actions must stay non-executing for WordPress import, while a chained local `/GoTo` target still supplies review context such as PageLabels, destination view operands, page `/Dur`, page `/Trans`, page `/AA`, and article-thread beads.

## Red Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php`

failed on the new fixture because the root `/Launch` action row did not inherit the chained local destination context:

- Expected `destination_action_target_page`: `1`
- Actual: `NULL`
- Result: `1 test files, 20 assertions, 1 failures`

The visible-text isolation test already passed, so the gap was context propagation only.

## Implementation

- `PdfOutlineExtractor::shouldApplyActionChainTargetContext()` now allows review-only blocked Launch rows to receive resolved action-chain target context when the chain contains a local destination.
- Added `PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php` with a PDF outline `/A` root `/Launch` action, a `/Next` local `/GoTo` destination, a URI followup, a target page with `/Dur`, `/Trans`, and `/AA`, PageLabels, and catalog article `/Threads`.
- Added `wordpress-pdf-outline-launch-thread-transition-context-currentbase.php` to prove the WordPress import path exposes review metadata while excluding launch filenames, destination names, URI operands, page-action operands, and article-thread titles from visible paragraphs.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php` failed before the source change: `1 test files, 20 assertions, 1 failures`.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php` passed: `1 test files, 76 assertions, 0 failures`.
- Adjacent outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `5 test files, 511 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-launch-thread-transition-context-currentbase.php` passed and emitted `launch_target_label="Article 18"`, `launch_target_transition="Fly"`, `launch_target_article_beads=[21,22]`, `all_outline_actions_review_only=true`, and `visible_text_excludes_launch_action_context_operands=true`.
- PHP lint passed for changed PHP files.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `718 -> 720` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `516 -> 517 / 78`.

## Non-Overlap

This does not repeat accepted outline destination-action context, destination Fit action chains, named destination action article-thread context, OpenAction thread/PieceInfo propagation, page transition extraction, article-thread navigation, or security Launch/URI certificate permission review. The bounded new behavior is specifically a root outline `/Launch` action row inheriting context from a chained local destination while remaining blocked and non-executing.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline/action walker, destination/name-tree resolver, page-label resolver, page transition/action parser, article-thread extractor, and visible text boundary. Full upstream runner parity remains blocked by the Python/PDF/model stack: pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
