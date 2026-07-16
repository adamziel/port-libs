## Embedded File Stream Type Attachment Boundary Current Base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T155704Z`

Accepted base: `f275e7ef84bb0d1552526667c009b35d687cc13a`

Behavior mapped:

- FileSpec `/EF` streams are attachment payload rows only when the stream `/Type`
  is omitted for legacy embedded files or explicitly `/EmbeddedFile`.
- Typed non-attachment streams such as `/Type /Metadata` or `/Type /XObject`
  referenced through `/EF` fail closed before WordPress attachment summaries,
  low-level embedded-file extraction, document metadata `embedded_files` review,
  and visible text extraction.
- Legacy untyped streams and typed `/EmbeddedFile` streams remain importable.

Source-truth note:

- This is native PDF parser boundary behavior for the markerPDF no-GPU scope.
  It ports the PDF FileSpec/EmbeddedFile stream contract used by searchable-PDF
  attachment review, not upstream OCR/model execution.

Red-first evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEmbeddedFileStreamTypeBoundaryCurrentBaseTest.php`
- Result before implementation: `1 test files / 1 assertions / 1 failure`
- Failure: expected 2 valid attachment rows but current base returned 4 rows by
  admitting typed `/Metadata` and `/XObject` streams as FileSpec payloads.

Verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEmbeddedFileStreamTypeBoundaryCurrentBaseTest.php`
- Result after implementation: `1 test files / 65 assertions / 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-attachment-ef-stream-type-currentbase.php`
- Smoke result: `attachment_count=2`, `embedded_file_count=2`,
  `metadata_embedded_file_count=2`, `metadata_decoy_rejected=true`,
  `xobject_decoy_rejected=true`, no Python/models/external PDF tools.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PDF
  object parser, stream decoder, attachment extractor, embedded-file extractor,
  and metadata review paths.

Root harness:

- Not run - isolated micro-slice.
