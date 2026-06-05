# markerPDF Embedded Files Associated Mirror Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T051156Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T051156Z`

Base accepted HEAD: `0050f4e914c4e6207953a8c269ec4ee0dec173ba`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF text through PDF text APIs; FileSpec attachment payload bytes are not visible text.
- Native no-GPU markerPDF attachment preflight summarizes PDF FileSpec references for WordPress review. A single FileSpec can be associated at catalog/page scope and also exposed as a FileAttachment annotation; those are mirror contexts for one payload, not separate import payloads.
- PDF associated-file relationships, page `/AF`, and annotation `/FS` review metadata must be preserved without executing actions, Python, OCR/models, external PDF tools, or promoting attachment payload bytes into Gutenberg content.

## Behavior

`PdfAttachmentExtractor` now lets an existing EmbeddedFiles name-tree row or catalog-associated FileSpec row accept page `/AF` and FileAttachment annotation mirror metadata when all references resolve to the same FileSpec/EmbeddedFile stream. This keeps one attachment summary row while preserving:

- catalog associated-file index and catalog object id;
- page associated-file marker, page number, page object id, and page `/AF` index;
- FileAttachment annotation object id, contents, and rectangle;
- FileSpec relationship, checksum, hash, size, MIME type, and no-model/no-external-tool flags.

The bounded behavior is specifically the case where the same FileSpec is referenced by catalog `/AF`, page `/AF`, and annotation `/FS` while the catalog has no `/Names /EmbeddedFiles` mirror for that FileSpec.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: the new case `dedupes associated FileSpec mirrors when EmbeddedFiles name tree is absent` failed with expected attachment count `1` and actual attachment count `3`.

## Focused Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 353 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
```

Result: `8 test files, 1060 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `attachment_count=6`, `associated_filespec_without_name_tree_deduped=true`, `associated_filespec_without_name_tree_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog/page `/AF` extraction, EmbeddedFiles name-tree mirror marking, direct FileSpec mirror dedupe through name-tree rows, FileAttachment annotation mirror marking, related-file `/RF` name-pair parsing, EOF-bounded object scanning, xref table/stream selection, generation repair, object-stream FileSpec selection, encrypted EFF redaction, portfolio/PieceInfo/XMP/OutputIntent metadata, or attachment checksum review. The new boundary is only deduping associated-file mirrors when there is no document EmbeddedFiles name-tree row for the FileSpec.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, FileSpec/EmbeddedFile stream resolution, checksum review, catalog/page/annotation traversal, and existing WordPress attachment smoke. GPU/model execution, live OCR, PDFium rendering, Surya/Texify/Torch model paths, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
