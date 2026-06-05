# markerPDF xref object-stream unfiltered stream-member current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T052503Z`

Session: `port-dev-markerpdf-object-xref-20260605T052503Z`

Base accepted HEAD: `300ec9ba84c673261512ddb2a6bb27d7aede632d`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF parsing to `pdftext`/pypdfium before WordPress-visible text is emitted. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDF object streams are containers for ordinary non-stream objects. PDFium validates `/Type /ObjStm`, `/N`, and `/First`, reads object-number/offset pairs, and parses selected archive members by index; top-level stream objects are not valid compressed object-stream members. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The focused fixture builds a current xref stream that selects object `5` as member `0` of unfiltered object stream `6`. That member is an illegal top-level stream object containing text operators, and the current direct page references it in `/Contents` after a valid direct content stream.

Before the parser change, `PdfTextExtractor::objectsFromObjectStreams()` expanded that unfiltered member and WordPress paragraph extraction emitted:

```text
Current unfiltered stream-member guard page
Unfiltered stream member leak
Object-stream stream member rejected
```

After the parser change, object-stream expansion rejects top-level stream-object members before adding them to the object map regardless of whether the carrier has `/Filter`. Ordinary object-stream member dictionaries with stream-looking text inside strings, arrays, comments, or nested dictionaries still pass through the accepted token-boundary paths.

While running the adjacent object-stream family, the existing stream-dictionary helper fixture exposed the related current-base xref selection boundary: the final `startxref` token pointed at whitespace immediately before the xref-stream object. Classic xref tables already skip whitespace at the selected offset; xref-stream lookup now does the same, provided the starting byte is not inside an existing direct object body. This keeps the same current xref stream authoritative for type-2 member rows, trailer `/Root`/`/Info`/`/Encrypt` metadata, and stream-dictionary operand review.

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stream-object members inside unfiltered xref-selected object streams before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current unfiltered stream-member guard page',
)
Actual: array (
  0 => 'Current unfiltered stream-member guard page',
  1 => 'Unfiltered stream member leak',
  2 => 'Object-stream stream member rejected',
)

1 test files, 1 assertions, 1 failures
```

Focused run after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS rejects stream-object members inside xref-selected object streams before WordPress text extraction
PASS rejects stream-object members inside unfiltered xref-selected object streams before WordPress text extraction

2 test files, 37 assertions, 0 failures
```

Adjacent parser fixture recovered by whitespace-normalized xref-stream lookup:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current object-stream stream dictionary helper generations before WordPress extraction

1 test files, 52 assertions, 0 failures
```

Adjacent object-stream/xref parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 551 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-unfiltered-stream-member-currentbase.php
```

emits `uses_current_direct_guard_page=true`, `object_stream_carrier_unfiltered=true`, `rejects_unfiltered_stream_object_member=true`, `stream_member_rejection_count=1`, `excludes_unfiltered_stream_member_text=true`, `excludes_stream_member_payload_text=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted filtered stream-member rejection, object-stream nested token parsing, object-stream header comments, skipped zero object-number header rows, incomplete header fail-closed behavior, offset-order body slicing, explicit type-2 member-index selection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, direct `/ObjStm` base preservation, unselected object-stream suppression, object-stream carrier generation recovery, compressed helper filter-chain expansion, stream-owned xref/startxref rejection, comment-owned member offsets, or literal-string member offsets.

The bounded behavior is specifically rejecting xref-selected unfiltered object-stream members whose member body is a top-level stream object, plus matching xref-stream offset normalization for a selected startxref that lands on leading whitespace before the current xref-stream object.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, object-stream decoder, stream-dictionary operand resolver, page content stream walker, review metadata path, and WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
