# markerPDF Embedded Files Direct FileSpec Mirror Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T025837Z`

Base accepted HEAD: `0cd05fb9fd0d549a9991ab9b451279d3120512bc`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible searchable-PDF text in the pdftext/PDFium page text path. Embedded file streams, FileSpec dictionaries, catalog/page associated-file arrays, and FileAttachment annotation file specs are review metadata for WordPress import, not visible Gutenberg paragraph text.
- PDF attachment preflight remains a native no-GPU boundary in this lane: summarize `/Names /EmbeddedFiles`, catalog/page `/AF`, and FileAttachment annotation FileSpec rows without executing Python, models, external PDF tools, attachment actions, or embedded payload bytes.
- FileSpec dictionaries can appear as direct dictionary values in name-tree, associated-file, and annotation positions. When those direct dictionaries point at the same selected EmbeddedFile stream, the WordPress preflight should merge the review contexts instead of counting the same payload multiple times.

## Implementation

`PdfAttachmentExtractor::documentAttachmentIndex()` now recognizes mirrors without a FileSpec object id. The existing indirect-object match is preserved. For direct FileSpec mirrors, the extractor matches the existing EmbeddedFiles name-tree row to catalog `/AF`, page `/AF`, and FileAttachment annotation candidates when the selected EmbeddedFile stream object id, filename, decoded byte length, and SHA-256 are the same.

This keeps the `/Names /EmbeddedFiles` row authoritative and adds:

- `associated_file_source=catalog_af`
- `page_associated_file_source=page_af`
- `file_attachment_annotation_source=page_annotation`
- page, catalog, and annotation review metadata

The raw attachment bytes remain omitted from `attachmentSummary()`.

## Red/Green Evidence

Red baseline after adding the focused case:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 285 assertions, 1 failures`

Failure: `dedupes direct FileSpec mirrors across EmbeddedFiles AF and annotations` reported `attachment_count=4` instead of the expected single merged attachment row.

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 319 assertions, 0 failures`

Adjacent attachment family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result: `2 test files, 709 assertions, 0 failures`

Smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `attachment_count=5`, `direct_filespec_mirror_deduped=true`, `direct_filespec_payload_omitted=true`, `catalog_associated_file_preflight=true`, `page_associated_file_preflight=true`, `related_file_preflight=true`, `terminal_eof_bounds_attachment_scan=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform FileSpec filename selection, `/EF` key preference, `/AFRelationship` role mapping, checksum and declared-size review, related-file rows, EmbeddedFiles `/Limits` pruning, indirect `/Names` arrays, catalog/page `/AF` extraction, FileAttachment annotation mirrors through indirect FileSpec objects, EOF-bounded object scanning, xref-selected attachment rows, object-stream FileSpec recovery, portfolio/PieceInfo/XMP/OutputIntent metadata, or attachment payload exclusion. The bounded behavior is only mirror dedupe when the FileSpec itself is inline/direct and therefore has no stable FileSpec object id.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, FileSpec parsing, selected embedded stream decoding, checksum review, attachment summary redaction, and existing WordPress smoke path. Full upstream OCR/model parity remains dependency-gated by pdftext/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers under the current no-GPU direction.
