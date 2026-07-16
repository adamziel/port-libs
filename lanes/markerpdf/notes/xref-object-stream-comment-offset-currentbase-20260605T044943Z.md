# markerPDF object-stream comment-offset xref boundary

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T044943Z`

Session: `port-dev-markerpdf-object-xref-20260605T044943Z`

Accepted base: `85a8ec3ff89faa51eab494d54b6f8b2309e6ac3a`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium before model execution. In the native no-GPU PHP lane, `PdfTextExtractor` owns the low-level PDF parser boundary that selects page dictionaries and content streams before Gutenberg paragraph output.

For PDF 1.5 object streams, xref-stream type-2 rows point to an object-stream carrier and a member index. The member table offset must resolve to a real member object token in the decoded object-stream data. A row whose member offset starts at `%` is comment-owned, not page-dictionary-owned, and must fail closed before WordPress import.

## Behavior

This patch adds a narrow guard in `PdfTextExtractor::objectStreamMemberOffsetHasTokenBoundary()` so a selected object-stream member offset that begins at a PDF comment marker is rejected. Offsets after skipped comments remain governed by the existing token-boundary scanner.

The focused fixture keeps a valid direct page and adds a compressed fake page dictionary after a comment inside an object stream. The xref-stream type-2 row for object `4` points at the `%` comment marker instead of the page dictionary token. Before the fix, the native extractor emitted:

- `Current comment-offset guard page`
- `Comment-offset compressed leak`
- `Comment-owned member ignored`

After the fix, only the current direct page reaches WordPress paragraph output. The xref object-stream review records `selection_policy=invalid_object_stream_member_offset`, `member_offset_token_boundary=false`, and `invalid_member_offset_rejection_count=1`.

## Verification

Red-first focused probe:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php`

Result before fix: `1 test files, 1 assertions, 1 failures`; actual lines included `Comment-offset compressed leak` and `Comment-owned member ignored`.

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php`

Result: `1 test files, 20 assertions, 0 failures`.

Adjacent object-stream/xref run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php`

Result: `10 test files, 161 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-comment-offset-currentbase.php`

Result: emitted one Gutenberg paragraph for `Current comment-offset guard page` and smoke metadata `uses_current_comment_offset_guard=true`, `excluded_comment_offset_compressed_leak=true`, `excluded_comment_owned_member=true`, `selection_policy=invalid_object_stream_member_offset`, `member_offset_token_boundary=false`, `invalid_member_offset_rejection_count=1`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-comment-offset-currentbase.php`
- `git diff --check -- lanes/markerpdf`

Root harness: not run - isolated micro-slice.

## Exclusion

The shell-expanded broader object-stream/xref family was not counted for this handoff because `PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php` has a pre-existing page-count failure on accepted base `85a8ec3ff89faa51eab494d54b6f8b2309e6ac3a`, reproduced with this patch reverted:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php`

Result: `1 test files, 5 assertions, 1 failures`; expected page count `1`, actual `0`. That fixture's final `startxref` points one byte before the xref-stream object and is a separate parser repair target.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, member-offset token-boundary scanner, text extractor, review metadata path, and WordPress smoke renderer. GPU/OCR/model execution, PDFium, Surya/Torch, Texify, live OCR, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted literal-string member-offset rejection, duplicate object-stream offsets, zero-width member-index ambiguity, skipped header rows, incomplete headers, xref-stream filter-chain helper resolution, object-stream carrier generation repair, `/Prev` free-carrier repair, hybrid owner generation selection, or stream-member rejection. The bounded behavior is specifically type-2 object-stream member offsets that start at `%` comment markers inside decoded object-stream data.
