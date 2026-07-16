# markerPDF xref object-stream signed header current-base slice

Slice: `markerpdf-object-stream-xref-parser-current-base-20260605T124020Z`

Session: `port-dev-markerpdf-object-xref-20260605T124020Z`

Base accepted HEAD: `ab10b47deab67d77a4019d8ab12eee9ff8089952`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates low-level parsing/text extraction to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates bounded page text to pypdfium/PDFium before markerPDF emits Markdown-visible text.
- At the native PHP parser boundary, PDF object-stream headers are pairs of integer objects. Non-negative integer tokens may use a leading plus sign; negative values remain invalid for object numbers and member offsets.

## Implemented Behavior

`PdfTextExtractor::readPdfUnsignedIntegerToken()` now accepts leading `+` on unsigned integer tokens while still rejecting negative values. This lets xref-selected object streams parse headers such as `+4 +0 +12 +N`, preserving the explicit xref-stream type-2 member index and the member-offset body boundary before WordPress paragraph rendering.

The focused fixture keeps a direct page and a current compressed page in an `/ObjStm` whose decoded header uses plus-signed object-number and member-offset integers. Before the fix, the object-stream member table was empty and WordPress import kept only the direct guard page. After the fix, native extraction imports both current pages and keeps the decoy compressed member out of visible text.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamSignedHeaderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL parses plus-signed object-stream header integers before WordPress text extraction
Expected: [Current signed-header guard page, Signed object-stream header page, Plus offset member parsed]
Actual: [Current signed-header guard page]

1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamSignedHeaderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS parses plus-signed object-stream header integers before WordPress text extraction

1 test files, 19 assertions, 0 failures
```

Adjacent object-stream/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamSignedHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 945 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-signed-header-currentbase.php
```

Smoke output reports `uses_signed_header_compressed_page=true`, `plus_offset_member_parsed=true`, `direct_guard_page_preserved=true`, `excluded_decoy_member=true`, `object_stream_member_count=2`, `selection_policy=explicit_member_index`, `strict_member_match=true`, `page_count=2`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted object-stream `/First` boundary validation, incomplete header-pair rejection, skipped zero object-number row index alignment, header comment parsing, literal/comment/nested member-offset rejection, duplicate member-offset rejection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, explicit type-2 member-index selection, object-stream generation/carrier ownership, stream-member rejection, stream dictionary operand-generation recovery, object-stream filter-chain operands, metadata object-stream offset ownership, EmbeddedFiles object-stream expansion, xref-stream `/Prev` repair, xref-stream `/W [0 ...]` default type handling, malformed `/W` or `/Index` fail-closed handling, or hybrid table/free-entry conflict handling.

The bounded behavior here is only plus-signed non-negative integer tokens in decoded `/ObjStm` headers before xref type-2 object-stream member selection.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, page-tree walker, text extractor, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of no-GPU scope and remains gated on live pdftext/pypdfium/PDFium execution, Surya/Torch/OCR model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model tooling.

## Next Task

Continue bounded native searchable-PDF parser work on non-overlapping xref repair, stream filters, font/CMap metrics, metadata/action review, annotations/forms, page geometry, image/filter metadata, or supplied-boundary conversion edges with focused PHP tests and a WordPress smoke.
