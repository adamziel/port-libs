# markerPDF EmbeddedFiles Attachment Annotation Mirror Boundary

Session: `port-dev-markerpdf-attachments-20260605T022515Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T022515Z`
Base accepted HEAD: `07e85bc266c70e1d63d55405bb91a273b57af138`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF content extraction through parser-backed pdftext/PDFium boundaries. Embedded FileSpec payloads and FileAttachment annotations are not visible paragraph text in that path.
- The native no-GPU PHP lane owns lightweight attachment preflight for `/Names /EmbeddedFiles`, catalog/page `/AF`, and page `/Subtype /FileAttachment` annotations. WordPress import needs one payload summary for a FileSpec while preserving every review surface that points to it.
- Catalog/page `/AF` mirrors already deduped against EmbeddedFiles name-tree entries. The missing current-base boundary was an annotation whose `/FS` points to the same FileSpec as the EmbeddedFiles name tree.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Before the source change, the new regression failed:

```text
FAIL marks FileAttachment annotation mirrors without duplicating EmbeddedFiles payload summaries
Expected: 1
Actual: 2
```

The preflight emitted separate rows for one payload instead of keeping the EmbeddedFiles row canonical and attaching page/annotation review metadata.

## Implementation

`PdfAttachmentExtractor` now marks page FileAttachment rows with `file_attachment_annotation=true`. When a FileAttachment annotation resolves to the same FileSpec object and embedded stream as an existing EmbeddedFiles name-tree row, the extractor merges the annotation page/object/rect/contents metadata onto that existing row and skips the duplicate payload summary.

The existing WordPress smoke `wordpress-pdf-attachments-preflight.php` now includes an additional annotation mirror and emits `file_attachment_annotation_mirror_preflight=true` plus `file_attachment_annotation_duplicate_payload_omitted=true`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Passed:

```text
1 test files, 284 assertions, 0 failures
```

Attachment-family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
```

Passed:

```text
5 test files, 818 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

The smoke emitted `attachment_count=4`, `file_attachment_annotation_mirror_preflight=true`, `file_attachment_annotation_duplicate_payload_omitted=true`, `indirect_embeddedfiles_names_array_preflight=true`, `terminal_eof_bounds_attachment_scan=true`, `related_file_payload_omitted=true`, `page_associated_file_payload_omitted=true`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/EF` key selection, `/AFRelationship` role mapping, checksum review, related-file `/RF` summaries, EmbeddedFiles `/Limits` pruning, indirect `/Names` arrays, EOF-bounded object scanning, current xref row selection, xref-stream object-stream FileSpec resolution, catalog/page `/AF` mirror marking, standalone FileAttachment annotation extraction, or full `PdfEmbeddedFileExtractor` portfolio/PieceInfo/XMP/OutputIntent review. The bounded new behavior is only deduping a page FileAttachment annotation mirror against the canonical EmbeddedFiles name-tree attachment while preserving annotation review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, page/annotation traversal, indirect object resolver, FileSpec parser, stream filter decoder, checksum review, and WordPress smoke pattern. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers; none were executed for this bounded native PHP slice.
