# Embedded Files Attachments Boundary Current Base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T064050Z`

Accepted base: `8b9a89d4d40dfee6bec490587ed2daf5b7734133`

## Scope

This patch keeps `PdfEmbeddedFileExtractor` aligned with the existing
attachment-summary encrypted EFF boundary. Direct EmbeddedFiles inventories,
catalog/page `/AF` entries, and FileSpec `/RF` related-file rows now consult the
selected trailer `/Encrypt` dictionary before decoding embedded-file streams.

When `/EFF` selects an encrypted, missing, unsupported, or malformed crypt
filter, the extractor preserves review identity:

- FileSpec name, description, relationship, file-spec object id, embedded-file
  object id, and EF/RF key.
- Related-file object references and related filenames when FileSpec strings
  are identity-filtered.
- Crypt-filter policy metadata with `raw_encrypted_bytes_exposed=false` and
  `executes_decryption=false`.

It suppresses payload-derived fields:

- `content`, `size`, `content_sha256`
- checksum/computed-checksum/match state
- declared size, decoded length, MIME type, filters, and payload dates

When `/StrF` selects an encrypted or fail-closed crypt filter, the direct rows
also redact FileSpec string metadata such as filename, description, portfolio
field values, and related filenames. If `/EFF` is identity-filtered, payload
hashes remain available while encrypted strings stay suppressed.

This is intentionally native PDF parser behavior only. It does not run OCR,
Surya, Texify, Torch, Python models, decryption, or external PDF tools.

## Evidence

Red-first before source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEncryptedEffBoundaryCurrentBaseTest.php
```

Result: `1 test files, 13 assertions, 1 failures`; direct embedded-file rows
had no `encrypted_payload_suppressed` flag and still decoded the stream.

Focused green after source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEncryptedEffBoundaryCurrentBaseTest.php
```

Result: `1 test files, 70 assertions, 0 failures`.

Adjacent encrypted attachment/direct embedded-file/security family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEncryptedEffBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php
```

Result: `11 test files, 1113 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-embedded-file-encrypted-eff-boundary-currentbase.php
```

Result: passes with `direct_payload_suppressed=true`,
`related_payload_suppressed=true`, `high_level_payload_suppressed=true`,
`payload_content_exposed=false`, `encrypted_document_text_extraction_blocked=true`,
`executes_decryption=false`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is required. The slice reuses existing native PDF
dictionary, trailer, object-reference, crypt-filter, and embedded-stream review
helpers in `lanes/markerpdf/src`.

## Non-Overlap

This slice does not repeat accepted attachment summary encrypted EFF coverage,
encrypted related-file attachment-summary rows, page-level `/AF` metadata,
stream filter decoding, xref repair, pdftext dictionary order, image/media
review, annotations, forms, OCR/model workers, or supplied-boundary table/equation
handoffs. It specifically closes the lower-level direct EmbeddedFiles inventory
path so WordPress import review cannot receive encrypted embedded-file bytes
from that API.
