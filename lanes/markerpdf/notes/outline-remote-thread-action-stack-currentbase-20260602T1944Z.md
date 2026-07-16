# Outline Remote Thread Action Stack Current Base

Micro-slice: `outline-remote-thread-action-stack-currentbase`

Base accepted HEAD: `9024ba0be2ff6606c593f2df91634d02452a6ff1`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates outline/bookmark traversal to the PDF engine through `doc.get_toc(max_depth=max_depth)` and returns title, level, and page index metadata.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` obtains TOC metadata separately from `pdftext.extraction.dictionary_output(...)`, so active outline/action operands must not become visible page text.
- At the native PDF boundary, a remote `/S /GoToR` outline action may have a `/Next` stack whose later local `/S /GoTo` target lands on an article-thread page. The remote destination remains a remote-document review row, while the local fallback target context is useful for WordPress navigation review.

Upstream references used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`

## Red Evidence

After adding the focused fixture and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php`

failed because the leading remote `GoToR` review row did not inherit `destination_action_target_page` from the local `/Next` target:

- Expected: `1`
- Actual: `NULL`
- Result: `1 test files, 31 assertions, 1 failures`

## Implementation

- `PdfOutlineExtractor::shouldApplyActionChainTargetContext()` now applies already-resolved local action-stack target context to leading remote `/S /GoToR` review rows when the bounded `/Next` stack contains a local target.
- The remote row still keeps its remote `file`, remote `destination`, `new_window`, and `page=null` fields; it is not promoted into the same-document TOC.
- Added `PdfOutlineRemoteThreadActionStackCurrentBaseTest.php` covering:
  - remote `GoToR` first action,
  - local `/Next` `GoTo` target through `/Names /Dests`,
  - chained JavaScript/URI followups,
  - target PageLabels, transition/action metadata, and catalog `/Threads` bead context,
  - visible text exclusion for remote files, JavaScript, URI, destination names, and thread titles.
- Added `wordpress-pdf-outline-remote-thread-action-stack-currentbase.php` to prove the WordPress path emits review-only metadata and clean page paragraphs without Python/model/raster/PDF action execution.
- Updated `lane-status.json` and the manifest native scenario/inventory row for the mapped behavior.

## Verification

- Red-first focused gate before source change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php`
  failed with `1 test files, 31 assertions, 1 failures`.
- Focused gate after source change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php`
  passed with `1 test files, 66 assertions, 0 failures`.
- Adjacent outline gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`
  passed with `6 test files, 546 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-outline-remote-thread-action-stack-currentbase.php`
  passed and emitted `remote_action_files=["remote-article.pdf"]`, `outline_action_types=["GoToR","GoTo","JavaScript","URI"]`, `remote_stack_target_label="Article 8"`, `remote_stack_target_beads=[31,32]`, `all_outline_actions_review_only=true`, and `visible_text_excludes_remote_stack_operands=true`.
- PHP lint passed for:
  - `lanes/markerpdf/src/PdfOutlineExtractor.php`
  - `lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php`
  - `lanes/markerpdf/examples/wordpress-pdf-outline-remote-thread-action-stack-currentbase.php`
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `737 -> 739` pass / `0` fail because the focused file adds two TestRunner PASS cases.
- Mapped markerPDF/PDF semantics move `526 -> 527 / 78`.

## Non-Overlap

This does not repeat accepted direct remote GoToR outline extraction, remote `/Dest` action dictionaries, GoToE transition review, local named-destination action thread context, destination Fit action chains, page transition/action extraction, OpenAction thread/PieceInfo review, article-thread bead extraction, or visible text extraction. The bounded behavior is specifically a remote-first outline action stack whose later local `/Next` action targets an article-thread page.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline walker, name-tree destination resolver, action review walker, PageLabels resolver, page presentation parser, catalog `/Threads` bead metadata, and visible text extraction boundary. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
