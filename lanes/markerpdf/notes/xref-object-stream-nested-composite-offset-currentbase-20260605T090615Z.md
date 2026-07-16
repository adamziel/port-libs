# markerPDF xref object-stream nested composite offset current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T090615Z`

Session: `port-dev-markerpdf-object-xref-20260605T090615Z`

Base accepted HEAD: `a02fc28d14bb45fe4a801d7566e5c298993e318f`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF parsing to `pdftext`/pypdfium before WordPress-visible text is emitted. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDF object-stream type-2 xref rows select a compressed-object member by object-stream number plus member index. Member offsets are relative to `/First` and must identify member object boundaries, not a byte position inside a nested dictionary or array. The native PHP fallback therefore fails closed when review/xref selection points inside a composite token.

## Behavior

The focused fixture builds a PDF 1.5 xref stream whose type-2 row for object `4` points into a nested `/Private << /Type /Page ... >>` dictionary inside the first object-stream member. The visible current page is a direct object and should remain authoritative.

Before the parser change, `PdfTextExtractor::objectStreamMemberOffsetHasTokenBoundary()` skipped literal strings, hex strings, and comments while walking to the claimed member byte offset, but it did not reject offsets located inside balanced dictionary or array tokens. The visible text path was already clean for this fixture, but xref review classified the nested dictionary offset as a selectable strict object-stream member.

After the parser change, the member-offset boundary walk also consumes balanced dictionaries and arrays and rejects offsets that land within them. The review payload now reports `invalid_member_offset_rejection_count=1`, `selection_policy=invalid_object_stream_member_offset`, and `member_offset_token_boundary=false`, while WordPress paragraph extraction keeps only:

```text
Current nested-composite guard page
```

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedCompositeOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects object-stream member offsets inside nested dictionaries before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamNestedCompositeOffsetCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 14 assertions, 1 failures
```

Focused run after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedCompositeOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects object-stream member offsets inside nested dictionaries before WordPress text extraction

1 test files, 25 assertions, 0 failures
```

Adjacent offset-boundary regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedCompositeOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS keeps explicit attachment object-stream indexes aligned across commented header rows
PASS rejects object-stream member offsets that point at PDF comments
PASS rejects object-stream member offsets that point inside literal strings
PASS rejects metadata object-stream member offsets inside literal strings before catalog review
PASS rejects object-stream member offsets inside nested dictionaries before WordPress text extraction

5 test files, 119 assertions, 0 failures
```

Adjacent object-stream xref parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS preserves direct xref stream owners when decoded rows form a compressed owner cycle
PASS rejects object streams whose First offset points into a member body
PASS fails closed on incomplete object-stream header pairs before WordPress text extraction
PASS uses object-stream member offsets instead of header order before WordPress text extraction
PASS keeps explicit object-stream member indexes aligned after skipped header rows
PASS keeps current object-stream base direct while applying explicit type-2 member index

6 test files, 101 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-nested-composite-offset-currentbase.php
```

emits `current_import_kept=true`, `nested_composite_decoy_excluded=true`, `invalid_member_offset_rejection_count=1`, `selection_policy=invalid_object_stream_member_offset`, `member_offset_token_boundary=false`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted literal-string member offsets, comment-owned member offsets, metadata literal offsets, attachment header comments, object-stream `/First` body offsets, skipped header rows, incomplete headers, offset-order body slicing, explicit type-2 member-index selection, compressed owner cycles, unfiltered stream-member rejection, stream-owned xref/startxref rejection, duplicate zero-width fail-closed behavior, direct `/ObjStm` base preservation, or compressed helper filter-chain expansion.

The bounded behavior is specifically rejecting xref-selected object-stream member offsets that land inside balanced nested dictionary or array composite tokens.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP xref-stream parser, object-stream decoder, balanced PDF token walkers, page text extractor, review metadata path, and WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
