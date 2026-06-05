# markerpdf xref prev-chain freed annotation current-base

## Scope

- Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T172306Z`
- Behavior: native annotation/link extraction now honors effective free rows from the current incremental xref `/Prev` chain before resolving `/Annots` references.
- Source truth: PDF incremental-update xref sections supersede earlier rows; a current free row for an object prevents stale previous-revision annotation dictionaries from satisfying current page references.

## Patch

- Added `PdfXrefFreeObjectMap`, a bounded parser for current xref free rows across classic xref tables and Flate/identity xref streams.
- `PdfAnnotationExtractor` and `PdfLinkAnnotationExtractor` now remove objects whose effective current xref row is free before walking page `/Annots`.
- Added a focused fixture where the current page still references `7 0 R`, but the current xref table marks object `7` free over a previous live link annotation.
- Added a WordPress smoke example proving the stale URI is not promoted into Markdown, annotation metadata, or span link fields.

## Evidence

- Red before source change:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php`
  - Result: failed with `https://stale.example.com/freed-annotation` promoted from object `7`.
- Passing focused verification after source change:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php`
  - Result: `3 test files, 60 assertions, 0 failures`
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php`
  - Result: `4 test files, 526 assertions, 0 failures`
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php`
  - Result: `7 test files, 508 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-freed-annotation-currentbase.php`
  - Result: emitted `current_xref_frees_annotation_object=true`, `link_pages=0`, `annotation_pages=0`, and `stale_annotation_promoted=false`.
- `git diff --check -- lanes/markerpdf`
  - Result: no whitespace errors.

## Dependency Closure

- No GPU/model/OCR, external PDF engine, browser, or live provider dependency is needed.
- The new support component is native PHP and lane-local under `lanes/markerpdf/src`.
- Follow-up: broaden this free-row guard to other independent annotation-style extractors only if a focused fixture shows they resolve stale freed annotation objects outside `PdfAnnotationExtractor` or `PdfLinkAnnotationExtractor`.
