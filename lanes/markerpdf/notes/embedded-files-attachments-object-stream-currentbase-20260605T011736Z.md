# markerPDF Embedded Files Attachment Object Stream Current Base

Session: `port-dev-markerpdf-attachments-20260605T011736Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T011736Z`
Base accepted HEAD: `c6112ce2e1611534e43d39ec57fc44e1f843be3a`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs; embedded-file payloads and FileSpec dictionaries are review metadata, not visible WordPress paragraph text.
- PDF xref-stream type-2 rows select ordinary compressed objects from `/Type /ObjStm` carriers by member index. FileSpec dictionaries may therefore be current even when no direct object definition exists for the FileSpec object number.
- The native PHP boundary remains no-GPU/no-model: WordPress attachment preflight summarizes current FileSpec rows without executing Python, models, external PDF tools, attachment actions, or raw payload promotion.

## Behavior

`PdfAttachmentExtractor` now expands selected xref-stream type-2 object-stream members into the lightweight attachment object map. This lets `/Names /EmbeddedFiles` and catalog `/AF` resolve a compressed FileSpec dictionary while still taking the embedded payload only from direct EmbeddedFile stream objects.

The expansion is bounded and fail-closed:

- `/ObjStm` carrier must be a selected direct stream with `/Type /ObjStm`, valid `/N`, and valid `/First`;
- explicit type-2 member indexes must match the requested object number;
- omitted index fields can recover by object-stream header object number;
- top-level stream-object members inside `/ObjStm` are rejected, since object streams carry ordinary non-stream objects.

The focused fixture includes a stale direct `4 0 obj` FileSpec plus a current xref-stream type-2 row for object `4` in filtered object stream `20`. Before the patch the lightweight preflight returned zero attachments. After the patch it reports `compressed-source.xml`, `AFRelationship /Source`, checksum match state, catalog `/AF` mirror metadata, and no stale filename or payload bytes.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes xref-stream object-stream FileSpec attachments before stale direct rows
Values are not identical
Expected: 1
Actual: 0
1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes xref-stream object-stream FileSpec attachments before stale direct rows
1 test files, 26 assertions, 0 failures
```

Focused attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
5 test files, 770 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachments-object-stream-currentbase.php
No syntax errors detected in all changed PHP files.

php lanes/markerpdf/examples/wordpress-pdf-attachments-object-stream-currentbase.php
emits attachment_count=1, object_stream_filespec_selected=true, stale_direct_filespec_excluded=true, payload_bytes_omitted=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted platform filename selection, `/AFRelationship` role mapping, checksum review, related-file `/RF` name pairs, catalog/page `/AF`, EmbeddedFiles name-tree `/Limits`, EOF-bounded attachment scanning, current direct xref row selection, full `PdfEmbeddedFileExtractor` portfolio/PieceInfo/XMP/OutputIntent review, text-extractor object-stream parser parity, or object-stream stream-member rejection for visible text. The bounded new behavior is only xref-stream type-2 compressed FileSpec dictionary resolution in the lightweight `PdfAttachmentExtractor` WordPress preflight.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, xref-stream reader, stream filter decoder, FileSpec parser, checksum review, and WordPress smoke pattern. Full markerPDF OCR/model parity remains intentionally out of scope under the current no-GPU direction.
