# Xref object-stream out-of-range member index boundary

Slice: `markerpdf-object-stream-xref-parser-current-base-20260606T010938Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF object/xref parsing to pdftext/PDFium before Marker converts blocks to downstream document content. At this native PHP boundary, xref-stream type-2 rows must treat the third field as the object-stream header member index. An explicit index outside the decoded `/ObjStm` header range is invalid and must not recover by object number or fall back to stale direct page text.

## Implementation

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now exposes explicit out-of-range type-2 member indexes as review metadata:

- `out_of_range_member_index_rejection_count`;
- per-entry `member_index_out_of_range`;
- per-entry `out_of_range_member_index_rejected`;
- selection policy `out_of_range_object_stream_member_index`.

The existing fail-closed extraction path remains intact: the invalid compressed member is not expanded, and a stale same-number direct page is still suppressed by the selected type-2 row.

## Fixture

The focused fixture builds:

- a current xref stream with object `4` as type 2 in object stream `6`, but with explicit member index `9`;
- object stream `6` with only two header members, including object `4` at actual index `1`;
- a stale direct object `4` page and a current direct guard page `8`.

Expected WordPress import text is only `Current out-of-range index guard page`; the compressed member text, explicit bad-index text, stale direct page text, and decoy first-member text are excluded.

## Red/Green Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php`

Result: `1 test files, 15 assertions, 1 failures`; the missing `out_of_range_member_index_rejection_count` review field failed.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php`

Result: `1 test files, 27 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-out-of-range-index-currentbase.php`

Result: emits `uses_guard_page_text=true`, `compressed_member_suppressed=true`, `stale_direct_page_suppressed=true`, `out_of_range_member_index_rejection_count=1`, `xref_member_index=9`, `object_stream_member_count=2`, `selection_policy=out_of_range_object_stream_member_index`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent xref object-stream family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php`

Result: `37 test files, 835 assertions, 0 failures`.

Syntax and patch checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-out-of-range-index-currentbase.php` => no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`.
- `git diff --check -- lanes/markerpdf` => clean.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, xref-stream parser, object-stream member table decoder, page-tree walker, and text extractor. Live OCR, Surya/Texify/Torch, pypdfium/PDFium execution, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU lane.

## Non-Overlap

This does not repeat accepted explicit type-2 member-index selection, zero-width omitted member-index recovery, duplicate zero-width header rejection, skipped/commented header rows, malformed `/First`, incomplete headers, duplicate member offsets, top-level stream-member rejection, direct object-stream base preservation, omitted-carrier repair, current free-carrier repair, `/Prev` carrier inheritance/replacement, xref-stream width/index operand validation, hybrid xref free/direct precedence, or stream-owned fake xref/object owner boundaries. The bounded behavior is only review-visible rejection of an explicit type-2 member index that is outside the selected object stream's decoded header range.
