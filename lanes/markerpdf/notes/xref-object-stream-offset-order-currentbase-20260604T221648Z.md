# markerPDF xref object-stream offset order current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260604T221648Z`

Base accepted HEAD: `3b64d900f18785e772f5dafe77ed00b17c3cd341`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level object stream and xref parsing to pdftext/PDFium. The native PHP boundary therefore has to preserve PDF 1.5 xref-stream type-2 object-stream member ownership before any WordPress paragraph rendering, without Python, models, or external PDF tools.

PDF object-stream type-2 rows select a carrier object stream and a member header index, while each member body is owned by its declared byte offset in the decoded object-stream object-data section. Header rows can be parsed independently from offset order; slicing a member body by "next header row" can drop a valid current page when the later object body appears earlier in the object-data section.

## Implementation

`PdfTextExtractor::objectsFromObjectStreams()` now bounds each compressed member body by the nearest greater declared member offset, falling back to the decoded object-data length. The same offset-owned boundary is reused for linearized hint-table object-stream member exclusion and compressed object-stream helper operand recovery.

This keeps explicit xref type-2 header indexes strict, while avoiding a false dependency on sorted member offsets. Malformed out-of-range or zero-length member offsets still fail closed.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses object-stream member offsets instead of header order before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'First offset-order page',
  1 => 'Declared offsets own member bodies',
  2 => 'Second offset-order page',
)
Actual: array (
  0 => 'Second offset-order page',
)

1 test files, 1 assertions, 1 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses object-stream member offsets instead of header order before WordPress text extraction

1 test files, 17 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
PASS skips commented object-stream header numbers before member offset ownership
PASS recovers xref-selected object streams whose filter chain operands are compressed helpers
PASS fails closed on duplicate object-stream header numbers when xref member indexes are zero-width
PASS reviews zero-width xref object-stream member indexes recovered for current-base import
PASS uses object-stream member offsets instead of header order before WordPress text extraction
PASS keeps explicit object-stream member indexes aligned after skipped header rows
PASS keeps current object-stream base direct while applying explicit type-2 member index

7 test files, 114 assertions, 0 failures
```

The WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-offset-order-currentbase.php
```

emits `First offset-order page`, `Declared offsets own member bodies`, and `Second offset-order page`, with `executes_python_or_models=false`, `executes_external_pdf_tools=false`, `excludes_object_stream_dictionary_leak=true`, and `strict_dependency_rejection_count=0`.

## Non-Overlap

This does not repeat accepted object-stream header comment parsing, skipped zero object-number header rows, explicit type-2 member-index selection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, indirect `/Length`/`/Filter`/`/N`/`/First` recovery, object-stream carrier exclusion from fallback text, compressed helper filter-chain expansion, xref-stream `/Prev` carrier generation repair, hybrid object-stream owner precedence, or stream-owned fake xref/object-header rejection.

The bounded behavior is specifically object-stream member body slicing when header-index order differs from decoded object-data offset order.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, stream decoder, xref-stream parser, object-stream expander, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
