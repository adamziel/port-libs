# markerpdf xref Prev chain page-review indirect Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T024809Z`

Accepted base: `c0189ee9c433a90073c4136e67c4f8566a365749`

## Source truth

- Upstream/native PDF behavior: incremental updates may store the xref section `/Prev` value as an indirect numeric object, and a current update can still own same-generation page, content, and page-associated file objects even when the latest xref rows carry damaged zero offsets. The page-property extractor must resolve the indirect numeric `/Prev` helper before repairing current update rows, then inherit older rows only after the current section has been repaired.
- Existing markerPDF source already handled this style in other xref consumers. This patch makes `PdfPagePropertyExtractor` follow the same native parser boundary for page-level review metadata.

## Implementation

- `PdfPagePropertyExtractor::xrefEntriesAtOffset()` now resolves direct or indirect numeric `/Prev` values through direct objects available before the current xref section.
- Indirect helper resolution is exact-generation, latest-before-section, integer-only, and capped at 8 helper hops. Non-integer or missing helpers still fail closed to the previous behavior.
- Current update xref row repair remains bounded to objects between the resolved previous xref offset and the current xref section offset.

## Evidence

Red-first focused run after adding the new test and before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 1 failures`; page review extraction returned 0 rows for a fixture whose current xref stream used `/Prev 30 0 R`.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 0 failures`.

Adjacent page-property regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCurrentBaseTest.php`

Result: `3 test files, 299 assertions, 0 failures`.

Adjacent xref Prev chain regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `2 test files, 571 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-review-xref-prev-chain-indirect-prev-currentbase.php`

Result: exits 0 and reports `page_review_count=1`, `pieceinfo_batch_id=current-indirect-prev-review`, `associated_filename=current-indirect-prev-review.xml`, `associated_checksum_matches=true`, `uses_indirect_prev_helper=true`, `stale_prev_review_excluded=true`, and no Python/model or external PDF tool execution.

PHP lint:

- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-review-xref-prev-chain-indirect-prev-currentbase.php` => no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses native markerPDF direct-object scanning, xref stream/table parsing, FlateDecode handling, page PieceInfo extraction, and page-associated Filespec metadata extraction. GPU/OCR/model execution remains intentionally out of scope for this markerPDF no-GPU lane.

## Non-overlap

This is not the prior direct `/Prev` page-review repair slice, the general text/metadata/attachment indirect `/Prev` xref-chain slice, CMap/filter boundary work, OCR/model work, or dashboard/status-only movement. The new behavior is specifically page-property review metadata when the current xref section's `/Prev` operand is an indirect numeric helper.
