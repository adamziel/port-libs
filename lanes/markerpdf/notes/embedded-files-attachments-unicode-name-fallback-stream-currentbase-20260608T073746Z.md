# markerpdf embedded-files attachments unicode-name fallback stream current-base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T073746Z`
Base accepted HEAD: `cc8aff0c7fc799cc89f962c19a87ba076dddaa29`

## Source truth

PDF FileSpec dictionaries can expose platform/Unicode filename entries and an
`/EF` dictionary whose embedded-file stream keys mirror those filename keys.
The native no-GPU parser already chooses the selected filename source first and
falls back through `UF`, `F`, `Unix`, `Mac`, and `DOS` stream keys. This slice
adds review metadata for the WordPress import boundary where the selected
filename source is `/UF` but the only valid embedded-file stream is `/EF /F`.

## Behavior

- `PdfAttachmentExtractor::attachmentSummary()` now records
  `ef_key_selection_status=fallback_embedded_file_key` and
  `ef_key_preferred_source=UF` when a `/UF` filename is preserved but the
  payload stream is selected from fallback `/EF /F`.
- `PdfEmbeddedFileExtractor::extractEmbeddedFiles()` returns the same review
  metadata on the richer embedded-file row while still returning the decoded
  content for importer review.
- The WordPress smoke verifies the attachment summary omits payload bytes,
  visible text excludes embedded export XML, and no Python/models or external
  PDF tools execute.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentUnicodeNameFallbackStreamBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 10 assertions, 1 failures` due to
missing `ef_key_selection_status`.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentUnicodeNameFallbackStreamBoundaryCurrentBaseTest.php`

Result: `1 test files, 46 assertions, 0 failures`.

Adjacent attachment verification:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentUnicodeNameFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPlatformEmbeddedFileKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreeFallbackEfOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php`

Result: `6 test files, 1061 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-pdf-attachment-unicode-name-fallback-stream-currentbase.php`

Result: exits 0 and reports `unicode_filename_preserved=true`,
`payload_bytes_omitted_from_summary=true`, `visible_text_excludes_payload=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The slice reuses the native PDF FileSpec,
EmbeddedFiles name-tree, stream decoding, checksum, and text extraction
components already present under `lanes/markerpdf/src`.
