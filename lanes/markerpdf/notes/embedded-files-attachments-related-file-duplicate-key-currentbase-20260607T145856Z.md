# markerPDF Embedded Files Attachment Related File Duplicate Key Current Base

Session: `port-dev-markerpdf-attachments-20260607T145856Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T145856Z`
Base accepted HEAD: `8209e40a422edc00341bc56256bb3ab645e8d2d2`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs in `marker/pdf/extract_text.py`; embedded-file payloads and FileSpec related files are not part of that visible text path:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The native no-GPU PHP boundary is attachment review metadata only. A FileSpec `/RF` dictionary with duplicate platform keys, or a FileSpec with duplicate `/RF` entries, is ambiguous: a parser cannot safely decide which related sidecar name/stream pair is authoritative. WordPress import should keep the primary embedded attachment reviewable while suppressing ambiguous related-file rows and never promoting related-file payload bytes into visible text.

## Behavior

`PdfAttachmentExtractor` now checks the raw FileSpec dictionary before related-file review:

- duplicate top-level `/RF` entries suppress related-file rows while preserving the primary attachment;
- duplicate `/F`, `/UF`, `/DOS`, `/Unix`, or `/Mac` keys inside the resolved `/RF` dictionary suppress related-file rows;
- the primary EmbeddedFiles source attachment remains available with filename, AFRelationship, checksum, and no raw bytes in `attachmentSummary()`;
- related sidecar filenames, checksums, and payload bytes stay out of WordPress preflight summaries.

`PdfEmbeddedFileExtractor` now applies the same `/RF` duplicate-key boundary in the full embedded-file review path, so `extractEmbeddedFiles()` and `attachmentSummary()` agree.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFileDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate FileSpec RF platform keys before related-file attachment review (lanes/markerpdf/tests/PdfAttachmentRelatedFileDuplicateKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: false
Actual: true
FAIL fails closed on duplicate FileSpec RF dictionary entries while preserving primary attachment (lanes/markerpdf/tests/PdfAttachmentRelatedFileDuplicateKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: false
Actual: true

1 test files, 21 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFileDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate FileSpec RF platform keys before related-file attachment review
PASS fails closed on duplicate FileSpec RF dictionary entries while preserving primary attachment

1 test files, 64 assertions, 0 failures
```

Broader attachment-family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFileDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1193 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-related-file-duplicate-key-boundary-currentbase.php
Emits attachment_count=1, primary_filename=source.xml, primary_relationship=Source, related_files_suppressed=true, related_payload_bytes_omitted=true, visible_text="WordPress RF Duplicate Boundary Body", executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted related-file path review, related-file filename stream pairs, duplicate FileSpec filename or `/EF` key rejection, direct name-tree FileSpec duplicate-key rejection, annotation FileSpec duplicate-key rejection, platform EF key selection, Mac Params resource-fork review, encrypted related-file redaction, portfolio collection metadata, PieceInfo metadata, xref repair, EOF-bounded attachment scanning, or outline/navigation boundary work. The bounded behavior is only duplicate `/RF` entries and duplicate platform keys inside the FileSpec `/RF` related-file dictionary.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, raw dictionary duplicate-key scanner, FileSpec parsing, related EmbeddedFile stream review, checksum review, and WordPress smoke pattern. Full OCR, Surya/Texify/Torch, PDFium rendering, decryption, media playback, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
