# markerPDF Page Associated Files Transition Action

Slice: `page-associated-files-transition-action-currentbase-20260602T1601Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The manifest and accepted page-label/action notes map the upstream `marker/pdf/extract_text.py` boundary: markerPDF delegates PDF page iteration/text extraction to pdftext/pypdfium-style page objects, so native review metadata must stay page-aligned and separate from visible extracted text.
- The relevant PDF parser boundary is page dictionary metadata: `/AF` Filespec arrays, page `/Dur`, page `/Trans`, catalog `/PageLabels`, and page `/AA` additional-action dictionaries are metadata/review surfaces, not executable import content.

## Implementation

- `PdfPagePropertyExtractor::extractPageReviewMetadata()` now composes page review rows with existing `PdfOutlineExtractor::getPageTransitionActionMetadata()` output by page object number.
- Page rows that already report page `/AF`, `/PieceInfo`, or tagged UserProperties now carry `page_presentation` with one-based page number, page label, display duration, transition dictionary, and review-only page actions.
- The implementation reuses the accepted bounded action classifier instead of adding another PDF action parser; all action rows keep `executes_on_import=false`.
- Associated embedded-file payload bytes remain checksum/size review metadata only and are not exposed as visible text or row `content`.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed: `1 test files, 105 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `2 test files, 368 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-page-associated-transition-action-currentbase.php` passed and emitted `page_label=deck-7`, `page_associated_relationships=["Source","Alternative"]`, `transition_style=Fly`, `action_safety=["review-uri","blocked-unsafe-uri","remote-document-review"]`, `all_actions_review_only=true`, `excluded_associated_payload_text=true`, and `excluded_action_operand_text=true`.
- `php -l` passed for `PdfPagePropertyExtractor.php`, `PdfPagePropertyExtractorTest.php`, and `wordpress-pdf-page-associated-transition-action-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `533 -> 534`.
- Mapped native PDF semantics move `380 -> 381 / 78`.
- `UPSTREAM_TEST_MANIFEST.json` records `pdfPageAssociatedTransitionActionReviewBehaviors`.

## Non-Overlap

This does not repeat standalone page `/AF` Filespec extraction, catalog `/AF`, FileSpec `/PieceInfo`, page `/Dur` `/Trans` `/AA` extraction, catalog OpenAction review, outline target-page transition annotation, page-label extraction, or viewer-preference composition. The bounded behavior is the page-property review composition where page-associated files and page presentation/action metadata are emitted together for WordPress review while payload/action text remains excluded.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, page-tree ordering, FileSpec/embedded-file metadata extraction, page label parsing, transition parsing, action review, and visible text extraction. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
