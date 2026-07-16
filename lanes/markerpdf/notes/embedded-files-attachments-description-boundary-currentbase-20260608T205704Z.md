# Embedded File Attachment Description Boundary Current Base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T205704Z`

Base accepted HEAD: `65a813a175ece348dfcccbd33f271783300e8c24`

## Behavior

This patch keeps embedded attachment review payloads available while treating malformed FileSpec `/Desc` metadata as untrusted. Duplicate `/Desc` keys and tailed `/Desc` operands now omit the user-facing description and expose `description_status: malformed_filespec_description_omitted` instead. Valid single `/Desc` values are still preserved.

The boundary is metadata-only: attachment filenames, content bytes, embedded-stream checksums, and attachment counts remain available for review, while payload bytes and malformed descriptions remain out of WordPress-visible prose.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecDescriptionBoundaryCurrentBaseTest.php`

Failed before the source change with `1 test files, 8 assertions, 1 failures`; `PdfAttachmentExtractor` leaked the stale duplicate description.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecDescriptionBoundaryCurrentBaseTest.php`

Passed with `1 test files, 72 assertions, 0 failures`.

Focused family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php`

Passed with `74 test files, 4689 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-attachment-description-boundary-currentbase.php > /tmp/markerpdf-attachment-description-boundary-smoke.html`

Exited 0; the smoke reports `duplicate_description_status=malformed_filespec_description_omitted`, preserves the valid description, omits payload bytes from summary metadata, and keeps payload text out of visible blocks.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP FileSpec dictionary parsing, raw dictionary boundary guards, embedded-file stream decoding, and attachment summary extractors. It does not run Python, CUDA, OCR, models, raster rendering, pypdfium/PIL, external PDF tools, or live services.

## Follow-Up

Continue with non-overlapping native markerPDF parser work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
