# markerPDF xref object-stream next-offset boundary

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T120259Z`
Session: `port-dev-markerpdf-object-xref-20260605T120259Z`
Accepted base: `a467fce1e67c9dbaeea83429e2d75863f86d2075`

## Source Truth

Upstream `sddai/markerPDF` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level object-stream/xref recovery to pdftext/PDFium before OCR/layout/model paths. Under the current no-GPU scope, this PHP lane owns the native parser boundary that decides which compressed page objects become WordPress paragraphs.

PDF object-stream member offsets are relative to the first object byte. A malformed later header row can point inside an earlier member, for example into that page member's `/Contents` token. The native parser must reject the malformed later member without letting that bad offset truncate the earlier valid member.

## Implementation

`PdfTextExtractor::objectStreamMemberEndOffset()` now treats a later member offset as an end boundary only when that later member offset is itself a valid top-level token boundary. This preserves a valid earlier compressed page member while keeping the malformed later member rejected by the existing token-boundary guard.

Added focused coverage in `PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php` and a WordPress smoke in `wordpress-pdf-xref-object-stream-next-offset-boundary-currentbase.php`.

## Red First

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure: the compressed page member was truncated by the later malformed member offset, so only the direct guard page was extracted.

## Verification

Focused after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php`

Result: `1 test files, 18 assertions, 0 failures`

Adjacent object-stream/xref parser family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStream*Test.php`

Result: `40 test files, 758 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-next-offset-boundary-currentbase.php`

Result: emits `preserves_current_compressed_page=true`, `preserves_direct_guard_page=true`, `excludes_malformed_later_member_stream=true`, `invalid_member_offset_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

`php -l lanes/markerpdf/tests/PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-next-offset-boundary-currentbase.php`

Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, direct-object scanner, xref-stream decoder, object-stream header parser, stream decoder, and content-stream text extractor. GPU/OCR/model execution, Surya/Torch/Texify, pypdfium/PDFium runtime execution, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted object-stream `/First` boundary, incomplete header, duplicate offset, skipped header index, literal/comment/nested-composite member-offset rejection, offset-order slicing, type-2 index, omitted carrier, carrier generation, stream-member rejection, xref-stream operand owner, hybrid free-entry, or `/Prev` object-stream generation repair coverage. The bounded new behavior is only end-boundary selection for a valid earlier member when a later header offset points inside that earlier member.
