# Outline Destination Action Context Current Base

Micro-slice: `outline-destination-action-currentbase`

Base accepted HEAD: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark resolution to the PDF engine and exposes outline navigation as metadata: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks separate from TOC/navigation metadata: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- PDF outline `/Dest` values can resolve through `/Names /Dests` to action dictionaries. Chained `/Next` URI or JavaScript actions must stay non-executing, but import review UIs still need the resolved local target view and page-presentation context.

## Red Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php`

failed on the new fixture because chained destination-action rows had no `destination_action_target_view_mode`:

- Expected: `XYZ`
- Actual: `NULL`
- Result: `1 test files, 23 assertions, 1 failures`

## Implementation

- `PdfOutlineExtractor::destinationActionTargetContext()` now copies resolved destination target context onto action rows reached through outline destination action dictionaries:
  - `destination_action_target_view_mode`
  - `destination_action_target_view_position`
  - `destination_action_target_view_parameters`
  - `destination_action_target_display_duration`
  - `destination_action_target_page_transition`
  - `destination_action_target_page_actions`
  - existing article, page-review, and tagged-content target context remains preserved.
- Added `PdfOutlineDestinationActionContextCurrentBaseTest.php` with a named outline `/Dest` resolving to a `/S /GoTo` action whose `/D` points at a second named destination with `/XYZ` view parameters and whose target page has `/Dur`, `/Trans`, and page `/AA`.
- Added `wordpress-pdf-outline-destination-action-context-currentbase.php` to prove the WordPress path exposes review metadata while page paragraphs exclude URI, JavaScript, and destination/action operands.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php` failed before the source change: `1 test files, 23 assertions, 1 failures`.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php` passed: `1 test files, 60 assertions, 0 failures`.
- Adjacent outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `6 test files, 510 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-destination-action-context-currentbase.php` passed and emitted `chained_target_view_modes=["XYZ","XYZ","XYZ"]`, `chained_target_transition="Push"`, `all_outline_actions_review_only=true`, and `visible_text_excludes_action_context_operands=true`.
- PHP lint passed for the changed source, test, and example.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `640 -> 642` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `467 -> 468 / 78`.

## Non-Overlap

This does not repeat accepted outline `/Dest` action dictionary discovery, remote GoToR destination action review, named destination action article-thread context, target page-review propagation, catalog OpenAction destination action context, or page transition extraction. The bounded new behavior is specifically preserving resolved target view parameters and page-presentation metadata on chained action rows reached through an outline destination action dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, destination/name-tree resolver, action review walker, PageLabels resolver, page transition/action parser, and visible text boundary. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
