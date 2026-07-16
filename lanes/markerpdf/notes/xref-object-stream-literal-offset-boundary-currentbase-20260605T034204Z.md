# markerPDF xref object-stream literal-offset boundary current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T034204Z`

Session: `port-dev-markerpdf-object-xref-20260605T034204Z`

Base accepted HEAD: `b217639c020442991dfaab2d277ee4eb5848e531`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through parser-backed `pdftext`/PDFium extraction before OCR/model fallback. In this native no-GPU PHP lane, PDF 1.5 object-stream and xref-stream type-2 ownership is therefore parser dependency behavior before WordPress-visible text is emitted.

PDF object-stream headers declare each compressed member object number and byte offset into the decoded object-data section. A type-2 xref row can select a member index, but that index is only safe when the member offset starts at a real PDF token boundary. An offset that lands inside another member's literal string is malformed and must not promote string payload bytes into a page object.

## Behavior

The fixture creates an xref stream whose object `4` type-2 row explicitly selects member index `1` in object stream `6`. The selected header row points into object `12`'s literal string, where the string payload contains a fake page dictionary and content stream reference. Before this patch, the native parser expanded object `4` from that string interior and leaked `Literal-offset compressed leak` into WordPress paragraphs.

`PdfTextExtractor::objectStreamMemberBody()` now checks member offsets with a lexical token-boundary scan from `/First` through the decoded object-data section. Offsets inside literal strings, hex strings, comments, or the middle of bare tokens fail closed before compressed members enter the live object map. `extractXrefObjectStreamIndexReview()` now records `invalid_member_offset_rejection_count`, `member_offset_token_boundary`, `invalid_member_offset_rejected`, and `selection_policy=invalid_object_stream_member_offset` for review-only import metadata.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects object-stream member offsets that point inside literal strings (lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current literal-offset guard page',
)
Actual: array (
  0 => 'Current literal-offset guard page',
  1 => 'Literal-offset compressed leak',
)

1 test files, 1 assertions, 1 failures
```

Focused green run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects object-stream member offsets that point inside literal strings

1 test files, 20 assertions, 0 failures
```

Adjacent object-stream/xref parser sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfParserObjectStream*.php lanes/markerpdf/tests/PdfObjectStream*.php
Focused test run: 29 selected test files (root lock skipped)
29 test files, 501 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-literal-offset-boundary-currentbase.php
```

emits one Gutenberg paragraph, `Current literal-offset guard page`, plus review metadata showing `selection_policy=invalid_object_stream_member_offset`, `member_offset_token_boundary=false`, `invalid_member_offset_rejection_count=1`, `excluded_literal_offset_compressed_leak=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream header comment parsing, skipped zero object-number header rows, incomplete header fail-closed behavior, offset-order body slicing, duplicate member-offset rejection, explicit type-2 member-index selection, zero-width member-index recovery, duplicate zero-width fail-closed behavior, direct `/ObjStm` base preservation, object-stream carrier exclusion from fallback text, compressed helper filter-chain expansion, xref-stream `/Prev` carrier generation repair, hybrid object-stream owner precedence, or stream-owned fake xref/object-header rejection.

The bounded behavior is specifically an xref-selected object-stream member offset that points inside another compressed member's literal string payload.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, stream decoder, xref-stream parser, object-stream expander, lexical token readers, page-tree walker, content-token extractor, xref review metadata path, and WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
