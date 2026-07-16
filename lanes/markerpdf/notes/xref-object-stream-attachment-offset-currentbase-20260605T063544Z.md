# markerPDF object-stream xref parser current base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T063544Z`
Session: `port-dev-markerpdf-object-xref-20260605T063544Z`
Base accepted HEAD: `beecd573326eb942861636d36f425d3bf3ca3af6`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before model/OCR stages. Under the current no-GPU scope, this lane owns the native PHP parser boundary for xref-selected objects, object streams, EmbeddedFiles, and WordPress attachment review.
- PDF 1.5 object-stream member offsets are relative to the first object byte and must identify object boundaries. A type-2 xref row must not slice a FileSpec dictionary from inside another compressed object's nested dictionary.
- Malformed current xref tables can mark a direct `/Pages` root free while the current trailer catalog still references that scanned direct page-tree root. The native parser may repair that trailer root, but must not revive freed child pages or unselected object-stream members.

## Implementation

- `PdfAttachmentExtractor` now checks object-stream member offsets before expanding xref-selected compressed FileSpec objects. It skips literal strings, hex strings, nested dictionaries, arrays, and comments, then requires a PDF delimiter before the member offset.
- `PdfEmbeddedFileExtractor` now applies the same member-offset token-boundary guard before expanding compressed catalog/name-tree/FileSpec review objects.
- `PdfTextExtractor` now repairs only the current trailer catalog's direct `/Pages` root when that exact direct `/Pages` object is marked free by the current xref table. Page-tree child free rows remain suppressed, preserving existing free-entry ownership behavior.
- Stream payload objects remain excluded from object-stream expansion; this slice only prevents malformed attachment dictionaries from being sliced out of compressed object data.

## Red-First Evidence

Focused fixture:

- current direct EmbeddedFiles FileSpec `current.csv` plus a catalog `/AF` mirror;
- stale FileSpec `nested-stale.csv` embedded inside `/PieceInfo << /WP << /Private ... >> >>` of another object-stream member;
- xref-stream type-2 row for object `4` points to the nested dictionary offset.

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php
FAIL rejects object-stream attachment member offsets that point inside nested dictionaries
Expected: 1
Actual: 2
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects object-stream attachment member offsets that point inside nested dictionaries

1 test files, 38 assertions, 0 failures
```

Final focused boundary check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1623 assertions, 0 failures
```

Broader object-stream parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfParserObjectStream*.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 614 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-attachment-offset-currentbase.php
current_attachment_kept=true
nested_dictionary_filespec_excluded=true
stale_payload_excluded_from_review=true
payload_bytes_omitted_from_summary=true
invalid_member_offset_rejection_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted object-stream literal-string offset rejection, comment offset rejection, metadata offset rejection, free-entry conflict suppression, type-2 member-index repair, object-stream filter-chain operands, xref-stream `/Prev` generation repair, classic xref rebuild boundary work, or attachment object-stream selection before stale direct rows. The bounded new behavior is attachment/FileSpec expansion fail-closed when the selected object-stream member offset lands inside a nested compressed dictionary, plus current trailer page-tree root repair that does not expand freed children or unselected compressed members.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream parser, object-stream decoder, FileSpec/EmbeddedFiles attachment review extractors, stream filter decoders, and WordPress smoke path. Full upstream OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF directive: no Surya/Texify/Torch, PDFium rendering, external OCR, Streamlit/FastAPI workers, or live model benchmark runs were used.
