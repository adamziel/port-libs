# markerPDF encrypted attachment stream-type boundary

Session: `port-dev-markerpdf-attachments-20260608T232423Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T232423Z`
Base accepted HEAD: `98e8999bf9b8bc75393d3cdf7374793f03cbce9c`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF extraction through parser-backed PDF text/PDFium paths before OCR/model fallback. In this native no-GPU PHP lane, attachment payloads are review metadata, not visible WordPress text, and PDF FileSpec `/EF` streams must still be attachment streams even when trailer encryption suppresses payload decoding.

The existing unencrypted path already rejected typed non-`/EmbeddedFile` `/EF` streams while decoding bytes. The missing boundary was encrypted preflight: when `/EFF` marks embedded-file streams encrypted, `PdfAttachmentExtractor` skipped decoding and could trust a typed `/XObject` stream as an attachment row. The patch validates stream dictionary `/Type` before encrypted primary `/EF`, related-file `/RF`, and Mac resource-fork review rows are exposed.

## Behavior

- Encrypted FileSpec `/EF` streams with `/Type /XObject` are excluded before attachment summaries, embedded-file inventories, and WordPress file blocks.
- Valid `/Type /EmbeddedFile` rows remain review-visible with filename, FileSpec object id, stream object id, relationship metadata, and encryption policy.
- Encrypted payload bytes, declared sizes, checksums, hashes, and raw content stay suppressed until decryption support exists.
- Visible text extraction remains blocked for the encrypted fixture, and no Python, OCR, model, PDFium, raster, or external PDF tools are executed.

## Verification

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedStreamTypeBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects encrypted typed non-EmbeddedFile EF streams before attachment summaries

1 test files, 66 assertions, 0 failures
```

Adjacent encrypted/stream-type family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileEncryptedEffBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEmbeddedFileStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
```

Result:

```text
5 test files, 660 assertions, 0 failures
```

Full attachment/embedded-file focused family:

```sh
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfAttachment*Test.php' -o -name 'PdfEmbeddedFile*Test.php' -o -name 'PdfEmbeddedFiles*Test.php' \) | sort)
```

Result:

```text
80 test files, 5761 assertions, 0 failures
```

Syntax:

```sh
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentEncryptedStreamTypeBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-encrypted-stream-type-boundary-currentbase.php
```

Result: no syntax errors in all three files.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-attachment-encrypted-stream-type-boundary-currentbase.php
```

Result: exits `0` and emits `attachment_count=1`, `embedded_file_count=1`, `stream_object_id=21`, `xobject_decoy_excluded=true`, `encrypted_payload_suppressed=true`, `payload_hash_available=false`, `payload_bytes_omitted_from_summary=true`, `embedded_file_content_omitted=true`, `visible_text_blocked_without_decryption=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-filter stack decoding, typed non-`/EmbeddedFile` rejection for unencrypted payloads, encrypted EFF payload suppression, related-file path/duplicate handling, platform EF key selection, unknown EF key rejection, Mac Params review, FileSpec/PieceInfo/Portfolio metadata, page/catalog/annotation/StructElem `/AF` extraction, xref/object-stream repair, or attachment fallback-text exclusion. The bounded behavior is only stream-type validation before encrypted lightweight attachment review rows that bypass payload decoding.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, FileSpec/EF/RF parsing, stream dictionary review, encrypted attachment policy review, embedded-file extractor parity, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution, Surya/Texify/Torch, live workers, and exact upstream model benchmark parity remain intentionally outside the current markerPDF no-GPU scope.
