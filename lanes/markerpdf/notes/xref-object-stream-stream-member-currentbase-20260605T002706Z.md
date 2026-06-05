# markerPDF xref object-stream stream-member current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T002706Z`

Session: `port-dev-markerpdf-object-xref-20260605T002706Z`

Base accepted HEAD: `e957462801f56ba71c456a2d3a05444c3273f81f`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF parsing to `pdftext`/pypdfium before WordPress-visible text is emitted. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDF object streams are compressed-object containers for ordinary non-stream objects. PDFium validates `/Type /ObjStm`, `/N`, and `/First`, reads object-number/offset pairs, and parses the selected archive member by index; stream objects are not valid compressed object-stream members at this parser boundary. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The focused fixture builds a current xref stream that selects object `5` as member `0` of filtered object stream `6`. That member is an illegal top-level stream object containing text operators, and the current direct page references it in a `/Contents` array after a valid direct content stream.

Before this patch, `PdfTextExtractor::objectsFromObjectStreams()` expanded that member and WordPress paragraph extraction emitted:

```text
Current stream-member guard page
Compressed stream member leak
Stream objects rejected from ObjStm
```

After this patch, object-stream expansion rejects top-level stream-object members from filtered `/ObjStm` carriers before adding them to the object map. The parser still allows ordinary member dictionaries that contain stream-looking text inside literal strings, comments, arrays, or nested dictionaries, and it preserves the accepted unfiltered linearized object-stream repair fixtures. Review metadata now reports `stream_member_rejection_count=1`, `object_stream_carrier_has_filter=true`, `object_stream_member_is_stream=true`, and `stream_member_rejected=true` for the selected type-2 row.

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stream-object members inside xref-selected object streams before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current stream-member guard page',
)
Actual: array (
  0 => 'Current stream-member guard page',
  1 => 'Compressed stream member leak',
  2 => 'Stream objects rejected from ObjStm',
)

1 test files, 1 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stream-object members inside xref-selected object streams before WordPress text extraction

1 test files, 18 assertions, 0 failures
```

Adjacent object-stream/xref parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 183 assertions, 0 failures
```

Broader object-stream/xref sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'ObjectStream|Xref.*Object|Parser.*ObjectStream' | sort)
Focused test run: 37 selected test files (root lock skipped)
37 test files, 631 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-stream-member-currentbase.php
```

emits `uses_current_direct_guard_page=true`, `object_stream_carrier_has_filter=true`, `rejects_compressed_stream_object_member=true`, `stream_member_rejection_count=1`, `excludes_compressed_stream_member_text=true`, `excludes_stream_member_payload_text=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted object-stream nested token parsing, object-stream header comments, skipped zero object-number header rows, incomplete header fail-closed behavior, offset-order body slicing, explicit type-2 member-index selection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, direct `/ObjStm` base preservation, unselected object-stream suppression, object-stream carrier generation recovery, compressed helper filter-chain expansion, or stream-owned xref/startxref rejection.

The bounded behavior is specifically rejecting xref-selected filtered object-stream members whose member body is a top-level stream object, so illegal compressed content streams cannot leak text into WordPress paragraphs.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, page content stream walker, review metadata path, and WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
