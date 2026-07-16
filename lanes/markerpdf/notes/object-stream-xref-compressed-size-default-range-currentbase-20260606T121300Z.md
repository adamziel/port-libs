# Object Stream Xref Compressed Size Default Range Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260606T121300Z`

Base accepted HEAD: `259f1bb48b87b09ee9889b2d9331db2eb82715fb`

## Source Truth

- PDF xref streams default their row ranges from `/Size` when `/Index` is omitted.
- markerPDF upstream routes this through native PDF object loading before page text extraction; this lane keeps the PHP fallback native and no-GPU.
- Existing parser behavior already decoded a direct `/Size` helper for default ranges. This patch closes the review gap for `/Size` operands, matching the existing `/Filter` and `/Length` xref-selected owner review surface.

## Behavior Added

- `extractXrefStreamFilterLengthOwnerReview()` now includes `/Size` operand review data.
- The new fixture stores `/Size 30 0 R` where object `30` is itself a compressed object-stream member selected by a type-2 xref row.
- With no `/Index`, the decoded `/Size` value expands the default row range to object 30, allowing current compressed catalog/page/font objects to win before stale direct fallback text.
- The WordPress smoke emits Gutenberg paragraph output plus review metadata: `compressed_entry_count=5`, `decoded_entry_count=31`, `indirect_size_count=1`, and `size_operand_owner_policy=compressed_operand_after_xref_decode`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-compressed-size-default-range-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php` => 1 test files / 41 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php` => 3 test files / 103 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php` => 54 test files / 1199 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-xref-stream-compressed-size-default-range-currentbase.php` => emits two Gutenberg paragraphs and the expected native metadata comment.
- `git diff --check -- lanes/markerpdf` => clean.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP xref-stream parser, object-stream expander, Flate stream decoder, and operand owner review helpers. It does not invoke OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Python, or external PDF tools.

## Non-Overlap

This does not repeat the accepted `/W`/`/Index` indirect helper, nested helper object-stream, `/Prev` chain, Link annotation optional-content, or CMap filter-owner slices. The new behavior is the xref-stream `/Size` default-range path when the `/Size` operand itself is a current compressed object-stream helper.
