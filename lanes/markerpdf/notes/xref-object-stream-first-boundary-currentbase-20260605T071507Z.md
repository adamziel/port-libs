# markerPDF xref object-stream First boundary current-base slice

Slice: `markerpdf-object-stream-xref-parser-current-base-20260605T071507Z`

Session: `port-dev-markerpdf-object-xref-20260605T071507Z`

Base accepted HEAD: `a99415aeed2feb39fb6e42a4ec05fd4b05a42134`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates parser/text extraction to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates bounded page text to pypdfium/PDFium before markerPDF emits Markdown/WordPress-visible text.
- PDFium object-stream parsing validates `/Type /ObjStm`, `/N`, and `/First`, then parses member objects through object-stream archive offsets. At this native PHP dependency boundary, bytes between the consumed `/N` header pairs and `/First` are still header bytes; if they contain object data, the member table is malformed and must fail closed before WordPress paragraph extraction.

## Implemented Behavior

`PdfTextExtractor::objectStreamHeaderMembers()` now verifies that the decoded object-stream header is fully consumed after the declared `/N` object-number/offset pairs, allowing only PDF whitespace/comments before `/First`. The same consumed-header guard is mirrored in the metadata and embedded-file object-stream member parsers so catalog metadata and embedded-payload review paths do not select malformed members differently from visible-text extraction.

The focused fixture declares `/N 1` with header pair `4 0`, but sets `/First` to the start of a fake page dictionary embedded inside the first member's literal string. Before the fix, member offset `0` was accepted because it equaled `/First`, so WordPress text extraction emitted `First-boundary compressed leak` and `Malformed First member ignored`. After the fix, the object-stream member table is rejected, the current direct guard page remains the only visible page, and review metadata reports `selection_policy=missing_object_stream_member`.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects object streams whose First offset points into a member body (lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current First-boundary guard page',
)
Actual: array (
  0 => 'Current First-boundary guard page',
  1 => 'First-boundary compressed leak',
  2 => 'Malformed First member ignored',
)

1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects object streams whose First offset points into a member body

1 test files, 20 assertions, 0 failures
```

Adjacent object-stream/xref plus metadata/embedded-file parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 23 selected test files (root lock skipped)
23 test files, 895 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-first-boundary-currentbase.php
```

Smoke output reports `uses_direct_guard_page=true`, `excluded_malformed_first_page=true`, `excluded_malformed_first_member_text=true`, `excluded_fake_page_after_first=true`, `object_stream_member_count=0`, `selection_policy=missing_object_stream_member`, `strict_dependency_rejection_count=1`, `page_count=1`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted incomplete object-stream header-pair rejection, skipped zero object-number row index alignment, header comment parsing, literal/comment/nested member-offset rejection, duplicate member-offset rejection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, explicit type-2 member-index selection, object-stream generation/carrier ownership, stream-member rejection, stream dictionary operand-generation recovery, object-stream filter-chain operands, metadata object-stream offset ownership, EmbeddedFiles object-stream expansion, xref-stream `/Prev` repair, or hybrid table/free-entry conflict handling.

The bounded behavior here is specifically `/First` boundary validation after declared object-stream header pairs: a malformed `/First` that points into compressed object data cannot turn member offset `0` into a valid page object.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, metadata/embedded-file review paths, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of no-GPU scope and remains gated on live pdftext/pypdfium/PDFium runtime execution, Surya/Torch/OCR model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model tooling.

## Next Task

Continue bounded native searchable-PDF parser work on non-overlapping xref repair, stream filters, font/CMap metrics, metadata/action review, annotations/forms, page geometry, image/filter metadata, or supplied-boundary conversion edges with focused PHP tests and a WordPress smoke.
