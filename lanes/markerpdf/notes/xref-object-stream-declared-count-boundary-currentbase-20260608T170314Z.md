# xref object-stream declared-count boundary current-base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260608T170314Z`

Base: `8cfcace1e084b63a70b860b56a12c6af9ea20437`

## Source Truth

- Upstream inventory source remains `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- PDF object streams use `/N` as the declared number of compressed object members and `/First` as the offset to the first object body. Xref-stream type-2 rows then select members by object stream number and member index.
- Under the current no-GPU markerPDF scope, this slice only changes native PHP object-stream/xref parsing before searchable-PDF WordPress text extraction. It does not run OCR, models, Python workers, PDFium, raster rendering, or external PDF tools.

## Behavior

- `PdfTextExtractor::objectStreamHeaderMembers()` now keeps `/N` authoritative for the importable member table.
- Complete extra unsigned integer-pair data after the declared `/N` header pairs is tolerated as a header overrun, but it is not admitted into the member table.
- Incomplete or malformed header tails still fail closed, preserving the existing incomplete-header guard.
- Xref type-2 rows that point at an ignored overrun pair remain review-visible as `out_of_range_object_stream_member_index` and do not leak WordPress text from stale compressed page members.

## Red-First Evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure: expected `Current declared-count page` and `Ignored header overrun`, but extraction returned an empty text-line list because the whole object stream was rejected.

## Verification

`php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-declared-count-currentbase.php`

Result: no syntax errors detected in all three changed PHP files.

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateObjectNumberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php`

Result: `8 test files, 176 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php`

Result: `84 test files, 1851 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-declared-count-currentbase.php`

Result: exits 0 and emits `uses_declared_current_page=true`, `excluded_overrun_page=true`, `excluded_decoy_member=true`, `compressed_entry_count=2`, `out_of_range_member_index_rejection_count=1`, `overrun_member_policy=out_of_range_object_stream_member_index`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

`git diff --check -- lanes/markerpdf`

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat incomplete object-stream header rejection, duplicate object-number rejection, duplicate offset rejection, zero-width member-index recovery, explicit out-of-range member-index rejection, malformed selected member-tail rejection, stream-member rejection, indirect wrapper rejection, object-stream member offset token-boundary checks, xref-stream malformed `/W` or `/Index` guards, row-alignment rejection, omitted graph repair, inherited carrier repair, Prev/hybrid generation ownership, or xref free-row owner behavior.

The bounded behavior is only a declared `/N` count boundary: complete extra integer-pair bytes before `/First` are ignored as non-importable header overrun data while declared members remain available.

## Dependency Closure

No new support component is needed. The patch reuses the existing native xref-stream decoder, object-stream member table parser, unsigned integer token reader, stream-filter decoder, text extractor, and xref object-stream review API. GPU/OCR/model execution, Python/pdftext workers, PDFium/PIL rendering, and external PDF tools remain intentionally out of scope.
