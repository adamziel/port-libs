# parser-xref-stream-filter-length-owner-review-currentbase-20260602T1653Z

Base accepted HEAD: `16897955fedbe8eb586eccc43fee984b6415532f`

## Source truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF text extraction to `marker/pdf/extract_text.py`, pdftext, and PDFium before WordPress-style Markdown emission.
- This native slice keeps that upstream boundary local: xref streams must decode from current object ownership before page text import, and review metadata must not execute Python, pdftext, pypdfium, PDF actions, JavaScript, models, raster engines, or external PDF tools.
- The focused PDF fixture uses a current xref stream with indirect `/Filter 30 0 R` and `/Length 31 0 R` operands. Both helper objects are selected by the same current xref stream; stale fallback page text remains present in the bytes and must stay excluded.

## Behavior

- `PdfTextExtractor::extractXrefStreamFilterLengthOwnerReview()` reports review-only metadata for xref-stream filter and length operands.
- The review records direct versus indirect operands, selected object ownership, preview values, declared length, filter names, decoded entry count, and a combined owner policy.
- Existing extraction behavior remains native and current-base: the current xref-selected page text is emitted, stale operand-owner page text is excluded, and no external runtime is invoked.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` - passed
- `php -l lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php` - passed
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-filter-length-owner-review-currentbase.php` - passed
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php` - passed, `1 test files, 30 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-xref-stream-filter-length-owner-review-currentbase.php` - passed; smoke emitted `xref_selected_operand_count=2`, `filter_length_owners_reviewed=true`, and `excluded_stale_xref_operand_owner_page=true`
- Adjacent parser/xref gate passed: `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` - passed, `8 test files, 686 assertions, 0 failures`

Final whitespace and JSON validation were run after metadata edits:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
- `git diff --check -- lanes/markerpdf`

## Non-overlap

This does not repeat the accepted xref-stream DecodeParms slice, object-stream filter-chain operand recovery, stream-owned xref object owner boundary, stream DecodeParms owner boundary, zero-width object-stream member review, free-entry repair, or structure-tree metadata rebase. It is limited to review metadata for xref-stream `/Filter` and `/Length` operand owners selected by the current xref table/stream.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF tokenizer, direct object table, xref-entry parser, stream-filter decoder, and WordPress paragraph smoke path under `lanes/markerpdf/**`.

## Next

Continue parser/xref closure with bounded current-base cases around xref-stream operand ownership, object-stream recovery, generation precedence, and stream-filter fail-closed boundaries.
