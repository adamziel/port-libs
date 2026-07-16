# markerPDF Embedded Files FileSpec Metadata Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T062438Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T062438Z`

Base accepted HEAD: `54d248e648f22ef3797e5b0638b5df06fc726604`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through PDF text APIs; embedded FileSpec payloads and attachment review dictionaries are not promoted into visible text.
- PDF FileSpec dictionaries can carry `/FS`, `/ID`, and `/V` metadata alongside `/EF`. WordPress import preflight needs those fields as review metadata, especially URL-backed source attachments and volatile export packets, without dereferencing URLs, executing actions, or exposing embedded bytes.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now carry FileSpec-local review fields on embedded attachment rows:

- `/FS /URL` is recorded as `file_system=URL` and `file_system_status=external_url_file_system_review_only`;
- `/ID [<permanent> <changing>]` is recorded as binary-safe hex under `file_identifier`, with complete/partial pair status;
- `/V true|false` is recorded as `volatile` plus `volatile_status`;
- attachment summaries still omit raw `bytes`, encrypted FileSpec string policy redacts `/ID`, and no Python/models/external PDF tools execute.

## Red-First Evidence

Before the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php
```

Result: `1 test files, 16 assertions, 2 failures`.

Both focused cases failed because `file_system` was absent from lightweight `PdfAttachmentExtractor` summaries and full `PdfEmbeddedFileExtractor` rows.

## Focused Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php
```

Result: `1 test files, 43 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php
```

Result: `9 test files, 1142 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `direct_filespec_file_system_review=true`, `direct_filespec_identifier_review=true`, `direct_filespec_volatile_review=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform filename selection, `/AFRelationship` mapping, checksum review, catalog/page `/AF` mirror marking, `/RF` related-file name pairs, `/Limits` pruning, terminal EOF object bounding, xref-selected attachment rows, encrypted EFF payload suppression, or Portfolio/PieceInfo/XMP/OutputIntent metadata. The bounded behavior is only FileSpec `/FS`, `/ID`, and `/V` metadata on already-selected embedded attachment rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parsers, FileSpec dictionaries, embedded stream decoding, checksum review, and existing WordPress attachment preflight smoke. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
