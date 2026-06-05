# markerPDF FileAttachment Annotation Presentation Boundary

Session: `port-dev-markerpdf-attachments-20260605T070300Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T070300Z`
Base accepted HEAD: `a082cab10bdb18b88ae8978f2779c698a9d629b2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through pdftext/PDFium-style page text boundaries; embedded FileSpec payload streams and FileAttachment annotation state are review metadata, not visible paragraph text.
- PDF FileAttachment annotations carry presentation state through annotation `/F` flags and `/Name` icon names. WordPress import preflight needs that metadata to distinguish visible attachment markers from hidden/no-view review packets without executing actions, launching models, or exposing embedded payload bytes.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php
```

Failed before the source change:

```text
1 test files, 8 assertions, 1 failures
```

The missing field was `annotation_flags` on the canonical EmbeddedFiles row after a page FileAttachment annotation mirror resolved to the same FileSpec.

## Implementation

`PdfAttachmentExtractor` now records FileAttachment annotation presentation metadata:

- `/F` is exposed as `annotation_flags`, `annotation_flag_names`, `annotation_visibility`, `annotation_visible`, `annotation_hidden`, `annotation_printable`, and `annotation_no_view`.
- `/Name` is exposed as `annotation_icon`, `annotation_icon_label`, and `annotation_icon_status` for standard FileAttachment icons.
- Mirror dedupe now copies those annotation review fields onto the canonical attachment row when a FileAttachment annotation points to an already listed EmbeddedFiles FileSpec.
- Attachment summaries still omit raw `bytes`, avoid action execution, and keep Python/model/external PDF tools out of the native no-GPU path.

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php
```

Passed:

```text
1 test files, 40 assertions, 0 failures
```

Attachment-family sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed:

```text
10 test files, 1182 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-presentation-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-presentation-currentbase.php
```

The smoke emitted `visible_attachment_icon=Paperclip`, `visible_attachment_visibility=visible`, `hidden_attachment_icon=PushPin`, `hidden_attachment_visibility=hidden`, `hidden_attachment_no_view=true`, `payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree extraction, catalog/page `/AF`, FileAttachment payload extraction, annotation mirror dedupe, direct FileSpec mirror dedupe, FileSpec `/FS`/`/ID`/`/V` metadata, `/AFRelationship` role mapping, related-file `/RF` name pairs, EOF/xref/object-stream attachment selection, encrypted EFF redaction, or portfolio/PieceInfo/XMP/OutputIntent metadata. The bounded new behavior is only FileAttachment annotation presentation metadata on attachment preflight rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, page annotation traversal, FileSpec parser, embedded stream decoder, checksum review, attachment summary redaction, and WordPress smoke pattern. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
