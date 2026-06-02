# Outline Direct Named Thread Action Current Base

Micro-slice: `outline-named-dest-action-thread-rebase-currentbase`

Base accepted HEAD: `c3b759a859020b8775e124d837d858198d98558e`.

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` maps PDF engine outline rows through `marker/cleaners/toc.py::get_pdf_toc(doc, max_depth=15)`, preserving title, level, and page index from `doc.get_toc(...)`.
- Upstream page text extraction remains separate in `marker/pdf/extract_text.py::get_text_blocks()`, which returns `marker_blocks, toc` after `pdftext.extraction.dictionary_output(...)`.
- Relevant PDF behavior for this slice: a catalog `/Names /Dests` entry can point directly to an action dictionary. If an outline item uses `/Dest /ThreadAction` and that name resolves to `/S /Thread`, WordPress import should expose the Thread target and `/Next` followups as review-only action metadata, not as visible paragraph text or an executable viewer action.

## Implementation

- `PdfOutlineExtractor::shouldApplyActionChainTargetContext()` now lets local `/S /Thread` review rows inherit the same normalized `destination_action_target_*` context already attached to GoTo, GoToR, Launch, and chained action rows.
- Added a direct named-destination Thread fixture to `PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php`.
- Added `wordpress-pdf-outline-direct-named-thread-action-currentbase.php` smoke proving primary and chained action rows preserve page label, transition, article bead, and page-review attachment context while visible WordPress text excludes action operands and embedded review payloads.

## Evidence

Red-first before production fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php
1 test files, 51 assertions, 1 failures
FAIL normalizes direct named destination Thread action target context on the primary review row
Expected: 1
Actual: NULL
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php
1 test files, 81 assertions, 0 failures
```

Adjacent outline gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineThreadActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
9 test files, 778 assertions, 0 failures
```

Outline-only sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
18 test files, 1264 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-direct-named-thread-action-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-outline-direct-named-thread-action-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks passed; the smoke emitted 2123 bytes of WordPress block output; `git diff --check -- lanes/markerpdf` produced no output.

The focused PASS case count for this file moves from 3 to 4, adding 41 behavior assertions in this slice. The full markerPDF root harness was not run from this isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline named-destination GoTo action chains, name-tree GoTo-to-Thread action page-review propagation, direct outline Thread actions, remote thread action stacks, OpenAction thread PieceInfo, destination Fit operand normalization, page transition extraction, standalone article-thread bead navigation, or generic action-chain safety classification. The new behavior is the primary review row for an outline `/Dest` name whose name-tree value is directly a local `/S /Thread` action dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, name-tree destination map, outline/action walker, article-thread bead resolver, page presentation parser, page-review extractor, and WordPress smoke path. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, and benchmark tooling.
