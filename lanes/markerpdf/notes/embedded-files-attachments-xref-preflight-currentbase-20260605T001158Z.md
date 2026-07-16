# markerPDF Embedded Files Attachment Xref Preflight Current Base

Session: `port-dev-markerpdf-attachments-20260605T001158Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T001158Z`
Base accepted HEAD: `a227a39fdb58a8f8657363accdb74b31ff4570a6`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets visible searchable-PDF text through `pdftext.dictionary_output()` and pypdfium page text APIs in `marker/pdf/extract_text.py`; embedded FileSpec payloads are metadata/review inputs, not visible text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The PDF attachment parser boundary for this slice is current xref object selection. Classic xref tables and xref streams identify the in-use object body by byte offset; stale same-object FileSpec or EmbeddedFile definitions before the xref section must not override current attachment review metadata.

## Implementation

`PdfAttachmentExtractor` now builds a direct-object definition inventory with object offsets, then selects live objects through the latest `startxref` when it points to:

- a classic xref table, including `/Prev` fallback rows; or
- a direct xref stream with direct `/W`, `/Index`, `/Size`, and Flate/ASCIIHex filter support.

The existing no-xref fallback remains for simple fixtures. The lightweight `attachmentSummary()` still strips raw `bytes`, still marks catalog/page `/AF` mirrors, and still reports filename source, `/AFRelationship`, stream filters, declared size, SHA-256, MD5 checksum match state, and timestamps.

The focused tests add two stale same-object shapes:

- a classic xref table points object `4` and `5` to current FileSpec/EmbeddedFile definitions while stale same-number definitions appear before the table;
- a Flate xref stream points the same object numbers to current definitions while stale same-number definitions appear before the xref-stream object.

Both fixtures now keep the current attachment rows and exclude `stale-xref.csv`, `stale-xref-stream.csv`, and stale payload text from WordPress preflight summaries.

## Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachments-xref-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachments-xref-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 236 assertions, 0 failures
```

Adjacent attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
2 test files, 626 assertions, 0 failures
```

Current-base associated metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php
3 test files, 145 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-xref-currentbase.php
```

Result: emitted `xref_selected_current_attachment=true`, `stale_same_object_attachment_excluded=true`, `raw_payload_omitted=true`, `associated_file_mirror_marked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree `/Limits`, EOF-bounded object scanning, catalog `/AF` ingestion, page `/AF` mirror marking, related-file rows, FileSpec platform filename selection, `/AFRelationship` role mapping, checksum review, portfolio `/Collection`, FileSpec `/CI`, PieceInfo, XMP/OutputIntent, page-associated files, rich-media FileSpec, encrypted associated-file metadata, or the richer `PdfEmbeddedFileExtractor` current-xref metadata slices. The new behavior is only current xref table/xref-stream object selection inside the lightweight `PdfAttachmentExtractor` WordPress preflight path.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object/value parser, stream decoder, FileSpec parser, checksum review, and WordPress smoke pattern. Full upstream markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium rendering, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU micro-slice.
