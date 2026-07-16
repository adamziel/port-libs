# markerpdf outline no-page root review boundary current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T222400Z`
Base: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Behavior

`PdfOutlineExtractor::getNavigationReviewMetadata()` now preserves the already-sanitized outline-root `/Metadata` review when a malformed PDF has a catalog outline tree but no usable page tree. The no-page navigation payload still keeps outline rows, action rows, page presentations, and page review empty; only the payload-free root review is surfaced.

This matches the native no-GPU markerPDF boundary: outline metadata is review metadata, not document XMP and not visible WordPress text. The slice does not invoke Python, OCR/models, PDFium/PIL, multiprocessing, or external PDF tools.

## Red-first evidence

A local probe before the patch showed `PdfMetadataExtractor` returning `rejected_malformed_outline_root_metadata_operand` for a no-page outline root `/Metadata 8 0 R /Private /A 20 0 R`, while `PdfOutlineExtractor::getNavigationReviewMetadata()` returned no `outline_root_review` because it exited as soon as page objects were absent.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNoPageRootReviewBoundaryCurrentBaseTest.php`
  - `1 test files, 48 assertions, 0 failures`
- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfOutlineMetadataNoPageRootReviewBoundaryCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-no-page-root-review-currentbase.php`
  - no syntax errors
- `php lanes/markerpdf/examples/wordpress-pdf-outline-no-page-root-review-currentbase.php`
  - exits 0 with `navigation_sources=["outline_root_review"]`, `outline_rows=0`, `root_metadata_status="rejected_malformed_outline_root_metadata_operand"`, and `visible_text_excludes_outline_metadata=true`

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF object parser, `PdfMetadataExtractor` outline-root metadata boundary review, and `PdfTextExtractor` fallback text exclusion.

Root harness not run - isolated micro-slice.
