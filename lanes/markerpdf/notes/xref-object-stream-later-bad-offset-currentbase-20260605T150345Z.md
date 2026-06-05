# markerPDF object-stream later bad offset current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T150345Z`
Session: `port-dev-markerpdf-object-xref-20260605T150345Z`
Base accepted HEAD: `5e277f7985f08bbea655de828433799334fd1a1e`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before model/OCR stages. Under the current no-GPU scope, this lane owns the native PHP parser boundary for xref-selected objects, object streams, EmbeddedFiles, and WordPress attachment/metadata review.
- PDF 1.5 object-stream member offsets are relative to the first object byte. A malformed later type-2 xref row that points inside an earlier member must be rejected and must not truncate that earlier valid member body.

## Implementation

- `PdfAttachmentExtractor` now ignores later object-stream member offsets that do not start at a valid top-level token boundary when slicing a selected compressed FileSpec member.
- `PdfEmbeddedFileExtractor` and `PdfMetadataExtractor` now apply the same token-boundary check when calculating object-stream member end offsets, including compressed helper-object lookup paths.
- `PdfTextExtractor` already had this boundary behavior; the slice aligns attachment, embedded-file, and metadata review with the text-side parser.

## Red-First Evidence

Before the implementation, the new focused fixture failed because the valid compressed FileSpec was truncated by a later malformed type-2 offset inside its dictionary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores malformed later object-stream offsets when slicing current compressed attachments (lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 3 assertions, 1 failures
```

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores malformed later object-stream offsets when slicing current compressed attachments
PASS ignores malformed later object-stream offsets when slicing current compressed catalog metadata

1 test files, 57 assertions, 0 failures
```

Adjacent object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfParserObjectStream*.php lanes/markerpdf/tests/PdfObjectStream*.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php
Focused test run: 45 selected test files (root lock skipped)
45 test files, 911 assertions, 0 failures
```

Direct changed extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1876 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-later-bad-offset-currentbase.php
```

The smoke exits `0` and reports `current_attachment_kept=true`, `valid_filespec_not_truncated=true`, `later_bad_offset_excluded=true`, `embedded_payload_available_to_attachment_review=true`, `payload_bytes_omitted_from_summary=true`, `invalid_member_offset_rejection_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream literal-string, comment, nested-dictionary, duplicate-offset, offset-order, stream-member, free-entry, object-stream carrier, xref-stream `/Prev`, or attachment EF selection work. The bounded behavior is only that a malformed later object-stream member offset cannot become the end boundary for an earlier valid xref-selected compressed member in attachment, embedded-file, or metadata extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream parser, object-stream decoder, token-boundary helpers, attachment/EmbeddedFiles review extractors, metadata extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
