# markerPDF Embedded Files Attachment Filename Path Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T073720Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T073720Z`

Base accepted HEAD: `b1bc67413271f951f8fd8e1d27ffa2800e27f096`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDFium page text APIs; FileSpec names and embedded payload bytes are not promoted into visible text.
- PDF FileSpec dictionaries can use `/F`, `/UF`, `/DOS`, `/Mac`, and `/Unix` names plus `/EF` streams. Those names may be platform paths or URL file specifications. WordPress import needs a basename-safe review field instead of treating PDF-supplied paths as local storage paths.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now preserve the raw selected FileSpec filename while adding basename/path review metadata:

- `filename_leaf` is the path/URL basename;
- `filename_storage_name` is a basename-only ASCII-safe storage/display candidate;
- `filename_path_status` classifies `basename_only`, `relative_path_segments_review_only`, `absolute_path_review_only`, or `url_path_review_only`;
- `filename_has_path_segments`, `filename_contains_parent_segment`, `filename_absolute_path`, and `filename_url_scheme` expose review flags when applicable.

Encrypted FileSpec string redaction removes the derived filename review fields with the raw filename fields. Attachment summaries still omit raw `bytes`.

## Red-First Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFilenamePathBoundaryCurrentBaseTest.php
```

Before the implementation this failed with `1 test files, 9 assertions, 2 failures`. The primary behavior failure was the missing `filename_leaf`; the first red fixture also included unrelated catalog `/AF` mirrors in the full embedded-file extractor, so that fixture was tightened before implementing the filename review fields.

## Focused Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFilenamePathBoundaryCurrentBaseTest.php
```

Result: `1 test files, 48 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFilenamePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
```

Result: `7 test files, 1091 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `filename_path_review=true`, `filename_storage_name_review=true`, `filename_path_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/AFRelationship` mapping, checksum review, catalog/page `/AF` mirror marking, FileAttachment annotation dedupe, `/RF` related-file name pairs, FileSpec `/FS`/`/ID`/`/V` metadata, EOF-bounded object scanning, xref-selected attachment rows, object-stream attachment offset guards, encrypted EFF payload suppression, or Portfolio/PieceInfo/XMP/OutputIntent metadata. The bounded new behavior is only basename-safe review metadata for path-shaped and URL-like selected FileSpec filenames.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parsers, FileSpec dictionaries, embedded stream decoding, checksum review, and existing WordPress attachment preflight smoke. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
