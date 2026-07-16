# markerPDF Embedded Files PDFDocEncoding Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T152458Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T152458Z`

Base accepted HEAD: `0220dd4558ea5903a383929b21ada9236db370a7`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF string extraction to PDF text APIs; native attachment review must decode PDF text strings before WordPress import metadata.
- PDF FileSpec names, EmbeddedFiles name-tree keys, and related-file name pairs are PDF byte strings. Without a UTF-16 BOM, bytes are PDFDocEncoding, not raw UTF-8.
- Attachment payload streams remain review/import payloads, not visible Gutenberg paragraph text. This slice decodes names only; it does not execute Python, OCR/models, PDFium rendering, or external PDF tools.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now decode PDFDocEncoding byte strings for:

- `/Names /EmbeddedFiles` name-tree keys and `/Limits` comparisons;
- FileSpec `/UF` filenames selected before `/F`;
- FileSpec `/RF` related-file name/stream pairs.

`PdfEmbeddedFileExtractor` also dedupes indirect name-tree and catalog `/AF` mirrors by FileSpec object plus EmbeddedFile stream object when both are available, so a human-readable name-tree label and a different decoded `/UF` storage filename do not create duplicate review rows for the same payload.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPdfDocEncodingBoundaryCurrentBaseTest.php
```

Result: the new case failed after 2 assertions because the attachment filename decoded to replacement characters instead of the PDFDocEncoding bullet/dagger filename.

## Focused Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPdfDocEncodingBoundaryCurrentBaseTest.php
```

Result: `1 test files, 51 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*.php lanes/markerpdf/tests/PdfEmbeddedFile*.php
```

Result: `22 test files, 1896 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-pdfdocencoding-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `pdfdocencoding_names_decoded=true`, `filename_storage_name=WP--Import-.xml`, `stale_out_of_limits_attachment_excluded=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog/page `/AF` extraction, FileAttachment annotation mirrors, indirect name-tree string keys, platform EF key ordering, related-file name-pair parsing, path basename review, encrypted EFF redaction, stream filter/DecodeParms handling, xref table/stream selection, object-stream FileSpec selection, portfolio/PieceInfo/XMP/OutputIntent metadata, or attachment checksum review. The new boundary is specifically PDFDocEncoding byte-string decoding for attachment labels/filenames and object-identity dedupe when name-tree labels differ from FileSpec filenames.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, existing FileSpec/EmbeddedFile stream resolution, name-tree limit handling, checksum review, related-file review rows, WordPress attachment smoke path, and the lane's existing PDFDocEncoding map semantics. GPU/model execution, live OCR, PDFium rendering, Surya/Texify/Torch model paths, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
