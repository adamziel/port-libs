# markerPDF Embedded Files Attachment Annotation State Current Base

Session: `port-dev-markerpdf-attachments-20260605T133754Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T133754Z`
Base accepted HEAD: `d93cb59e263d5bec6bba4ac974f8dbb66ee5ed6a`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through pdftext/PDFium before model fallback. In this no-GPU native PHP lane, embedded attachment payloads remain review-only metadata for WordPress import and must not be promoted into visible text or require Python/model/external PDF tool execution.

PDF FileAttachment annotations use the common annotation dictionary surface for review state: `/T` title, `/Subj`, `/M`, `/NM`, `/C`, and `/CA` sit beside `/Contents`, `/Rect`, `/F`, `/Name`, and `/FS`. The attachment preflight already mirrored `/Contents`, icon, visibility, and page/object metadata onto EmbeddedFiles rows; this slice carries the remaining common review-state fields.

## Behavior

`PdfAttachmentExtractor` now includes these fields in FileAttachment annotation review metadata and in mirrored EmbeddedFiles summary rows:

- `annotation_title` from `/T`;
- `annotation_subject` from `/Subj`;
- `annotation_modified_at` from `/M`;
- `annotation_name` from `/NM`;
- `annotation_color`, `annotation_color_space`, and `annotation_color_component_count` from `/C`;
- `annotation_opacity` from `/CA`.

Encrypted FileSpec-string redaction now also removes the string annotation fields, while numeric color and opacity remain review metadata. Attachment payload bytes remain omitted from `attachmentSummary()`.

## Evidence

Red-first focused run after adding the assertion and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries FileAttachment annotation icon and visibility flags through attachment preflight
FAIL carries FileAttachment annotation review state through mirrored attachment summaries
Values are not identical
Expected: 'Migration reviewer'
Actual: NULL
1 test files, 47 assertions, 1 failures
```

Focused after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries FileAttachment annotation icon and visibility flags through attachment preflight
PASS carries FileAttachment annotation review state through mirrored attachment summaries
1 test files, 61 assertions, 0 failures
```

Broader attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1290 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-presentation-currentbase.php
```

The smoke emits `visible_attachment_title="Migration reviewer"`, `visible_attachment_subject="Source packet"`, `visible_attachment_modified_at="D:20260605133754Z"`, `visible_attachment_name="attach-review-1"`, `visible_attachment_color_space="rgb"`, `visible_attachment_opacity=0.5`, `payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted attachment checksum, AFRelationship, FileSpec path, platform `/EF` key, related-file `/RF`, encrypted EFF, portfolio collection, object-stream/xref repair, annotation icon/visibility, page/catalog `/AF`, indirect name-tree, duplicate-key, EOF, or stream-filter DecodeParms boundaries. The bounded behavior is only common FileAttachment annotation review-state metadata carried through lightweight attachment preflight and mirrored EmbeddedFiles rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, annotation dictionary parsing, FileSpec attachment preflight, and WordPress smoke pattern. Full upstream model parity remains intentionally out of scope under the current markerPDF directive: no live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI workers, or external PDF tools were run.
