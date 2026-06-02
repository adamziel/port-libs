# Outline Destination Fit Action Chain Current Base

Micro-slice: `outline-destination-fit-action-chain-currentbase`

Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark/TOC extraction to the PDF engine through `doc.get_toc(max_depth=max_depth)` and returns page-indexed TOC rows: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` returns extracted page blocks plus TOC metadata separately, so destination/action operands must stay out of visible WordPress page text: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- PDF destination arrays use Fit-family view modes such as `/FitR`, `/FitBH`, and `/FitB`; PDF action dictionaries can chain follow-up actions through `/Next`. This slice keeps the resolved destination view context on review rows without executing any action.

## Red Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php`

failed because chained Fit action rows lacked `destination_action_target_page`:

- Result: `1 test files, 22 assertions, 1 failures`

## Implementation

- `PdfOutlineExtractor::outlineActionReviewRows()` now derives the first local GoTo target context from direct outline `/A` actions and outline `/Dest` values that resolve to action dictionaries.
- New `actionChainTargetContext()` resolves the action `/D` destination through existing name-tree and Fit-family normalization, then carries page label, target page presentation, page actions, and `destination_action_target_view_*` fields into the GoTo row and bounded chained `/Next` rows.
- Context is only applied to local GoTo rows and their chained followups, so unrelated sibling actions do not inherit the destination target.
- Added `PdfOutlineDestinationFitActionChainCurrentBaseTest.php` with direct `/Dest` action, direct outline `/A` action, and named destination action fixtures covering `/FitR`, `/FitBH`, and `/FitB`.
- Added `wordpress-pdf-outline-destination-fit-action-chain-currentbase.php` to prove the WordPress path emits review metadata while URI, Launch, JavaScript, destination names, and action operands stay out of visible Gutenberg paragraphs.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php` failed before source change: `1 test files, 22 assertions, 1 failures`.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php` passed: `1 test files, 68 assertions, 0 failures`.
- Adjacent outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `8 test files, 602 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-destination-fit-action-chain-currentbase.php` passed and emits FitR/FitBH/FitB target view modes with `all_outline_actions_review_only=true`.
- PHP lint passed for changed PHP files.
- `php -r` JSON decode checks passed for `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` and `lanes/markerpdf/lane-status.json`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `679 -> 681` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `493 -> 494 / 78`.

## Non-Overlap

This does not repeat accepted named-destination Fit operand normalization, outline destination action transition rows, destination action target context, remote GoToR destination action review, name-tree Limits handling, or OpenAction thread/page-review propagation. The new behavior is specifically Fit-family target context propagation from direct and named outline GoTo action chains onto bounded `/Next` review rows.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, destination name-tree resolution, Fit-family view normalization, action review walking, PageLabels, page transition/action parsing, and visible text extraction. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
