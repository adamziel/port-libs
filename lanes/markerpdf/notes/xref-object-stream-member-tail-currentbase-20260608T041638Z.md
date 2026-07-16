# xref object-stream member-tail boundary current-base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260608T041638Z`

Base: `e8c43317726abb932805c171a399c58fb2c01c99`

## Source Truth

- Upstream inventory source remains `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- MarkerPDF searchable-PDF imports rely on native PDF parser text extraction before any OCR/model fallback. Under the current no-GPU scope, this slice only changes native xref/object-stream parsing and review metadata.
- PDF object-stream member bodies are object values selected by header offsets and xref type-2 indexes. A selected member with a valid next offset but extra top-level object-boundary tokens after the first value is malformed for this native importer and must fail closed before WordPress paragraph extraction.

## Behavior

- Added a selected-member boundary check for object-stream expansion: non-stream object-stream members must contain one top-level PDF value.
- Added recovery awareness for accepted malformed-later-offset fixtures: when a later member header offset is already invalid, a valid earlier member is trimmed to its first PDF value instead of rejected, unless the tail starts with object-boundary keywords such as `obj`, `endobj`, `stream`, `endstream`, `xref`, `trailer`, or `startxref`.
- Added xref object-stream review fields:
  - `malformed_object_stream_member_tail_count`
  - `object_stream_member_has_single_value`
  - `malformed_member_tail_recovered_by_invalid_later_offset`
  - `malformed_member_tail_rejected`
- Added a WordPress smoke for the same fixture, proving the malformed compressed page text stays suppressed and no Python/models or external PDF tools execute.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure showed visible text leaked from the malformed compressed member:

- `Malformed member-tail page leak`
- `Trailing object-stream operand accepted`

## Verification

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

Result: no syntax errors detected.

`php -l lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php`

Result: no syntax errors detected.

`php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-member-tail-currentbase.php`

Result: no syntax errors detected.

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php`

Result: `1 test files, 26 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php`

Result: `2 test files, 44 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php`

Result: `75 test files, 1591 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php`

Result: `2 test files, 655 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-member-tail-currentbase.php`

Result: exits 0 and emits `malformed_object_stream_member_tail_count=1`, `malformed_member_tail_rejected=true`, `malformed_member_has_single_value=false`, `later_member_has_single_value=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat omitted compressed graph repair, duplicate xref-stream rows, row alignment, malformed `/W` or `/Index` values, zero-width member-index repair, omitted/current carrier repair, inherited carriers, generation repair, previous hybrid owner suppression, stream-member rejection, offset token-boundaries, indirect wrapper rejection, later bad-offset recovery, duplicate offsets/object numbers, out-of-range indexes, or page-resource entry-tail boundaries.

This slice only rejects a selected object-stream member body when the selected member has a valid member offset/window but contains non-comment top-level boundary tokens after its first PDF value.

## Dependency Closure

No new support component is needed. The patch reuses the existing native xref-stream decoder, object-stream member table, token scanner, stream-filter decoder, text extractor, and xref object-stream review path. GPU/OCR/model execution, PDFium/PIL rendering, Python workers, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU lane direction.
