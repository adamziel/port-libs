# Outline Remote GoToE Transition Current Base

Micro-slice: `outline-remote-gotoe-transition-currentbase`
Base accepted HEAD: `4dc1f21b98948ff243f10a6054e126d012098006`

## Source Truth

- Upstream `sddai/markerPDF` keeps outline/bookmark navigation separate from visible page text through its TOC extraction path; native markerpdf review metadata should preserve that boundary.
- PDF `/S /GoToE` actions target embedded documents, with optional `/F` file specs, `/D` embedded-document destinations, `/NewWindow`, and `/T` target dictionaries. Existing markerpdf rich-media and AcroForm action review paths already classify GoToE as `embedded-document-review` without executing PDF actions.

## Implementation

- `PdfOutlineExtractor` now recognizes outline and name-tree destination action dictionaries with `/S /GoToE`.
- GoToE review rows extract Filespec review metadata, embedded destination view operands, `NewWindow`, and target dictionary relation/name/page/annotation fields.
- Embedded-document destination page indexes are recorded as `destination_page`, not local `page`, so they do not create same-document TOC rows or inherit current-document page labels/transitions.
- Chained local `/S /GoTo` actions after a GoToE row still resolve through the current document destination tree and inherit target page labels, page transitions, and review-only page actions.
- The WordPress smoke renders only visible page text plus review-only action summaries; embedded payload bytes, action operands, JavaScript, and URI strings stay out of paragraph text.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php` failed before the source change with `1 test files, 19 assertions, 1 failures`; GoToE actions were classified as `unsupported-action-review`.
- Focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php` passed with `1 test files, 49 assertions, 0 failures`.
- Adjacent outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed with `3 test files, 380 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-remote-gotoe-transition-currentbase.php` passed and emitted review-only GoToE action rows with visible text limited to the two page content streams.
- Syntax: `php -l` passed for `lanes/markerpdf/src/PdfOutlineExtractor.php`, `lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-outline-remote-gotoe-transition-currentbase.php`.
- JSON: `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` both decoded cleanly.
- Whitespace: `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This slice does not repeat remote GoToR outline extraction, local destination transition enrichment, catalog OpenAction review, page transition extraction, rich-media GoToE review, AcroForm GoToE review, or visible text extraction. `getRemoteGoToActions()` remains GoToR-only; GoToE is embedded-document review metadata.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF parser, outline walker, name-tree resolver, action-chain reviewer, page-label/page-transition metadata, Filespec parsing, and visible text extractor. Full upstream runner parity remains gated by Python/model/PDF dependencies and live benchmark/app/server execution that this isolated micro-slice did not run.
