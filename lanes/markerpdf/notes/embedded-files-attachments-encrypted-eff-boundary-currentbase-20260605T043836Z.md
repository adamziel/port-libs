# Encrypted EmbeddedFiles Attachment Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T043836Z`

Accepted base: `6aaf0f620e0a4ee5fbfffd3a2afb15e30bb56a45`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.dictionary_output()` and pypdfium-backed page text before the conversion pipeline. Under the current no-GPU lane scope, the native PHP boundary owns parser/preflight behavior for attachments and security metadata without running pdftext, PDFium, Surya/Torch, Texify, or external PDF tools.

PDF encryption dictionaries can declare separate crypt-filter roles for ordinary streams (`/StmF`), strings (`/StrF`), and embedded-file streams (`/EFF`). The existing metadata/security paths already redacted associated-file payload metadata when `/EFF` was encrypted. This slice applies the same boundary to the standalone `PdfAttachmentExtractor` summary path used by WordPress attachment preflight.

## Behavior

`PdfAttachmentExtractor` now derives a local encrypted-attachment policy from the selected trailer `/Encrypt` dictionary:

- encrypted `/EFF` streams produce review rows with object identity, source, relationship, and encryption policy, but no `byte_length`, `sha256`, checksum fields, dates, content type, filters, or payload bytes;
- identity `/EFF` streams can still expose checksum/hash metadata, but raw `bytes` are removed for encrypted documents;
- encrypted FileSpec strings redact `filename`, `filename_source`, `name_key`, `description`, and annotation contents in standalone attachment summaries;
- crypt filters with `/CFM /None` or `/Identity` are treated as identity filters, while AES/V2 filters suppress payload hashes.

## Red-First Evidence

Before the source patch, the focused test failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses encrypted EFF payload metadata in attachment preflight summaries
Values are not identical
Expected: 0
Actual: 49
FAIL keeps identity EFF payload hashes while suppressing encrypted FileSpec strings and bytes
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'identity-eff-source.xml',
)

1 test files, 5 assertions, 2 failures
```

## Verification

Focused new slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS suppresses encrypted EFF payload metadata in attachment preflight summaries
PASS keeps identity EFF payload hashes while suppressing encrypted FileSpec strings and bytes

1 test files, 78 assertions, 0 failures
```

Attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
21 PASS cases
6 test files, 558 assertions, 0 failures
```

Encrypted attachment/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS suppresses encrypted EFF payload metadata in attachment preflight summaries
PASS keeps identity EFF payload hashes while suppressing encrypted FileSpec strings and bytes
PASS treats named crypt-filter CFM None as unencrypted review metadata while page text remains blocked
PASS inherits omitted EFF from StmF before encrypted permission and attachment preflight
PASS preserves unencrypted root XMP while blocking encrypted associated FileSpec metadata and OutputIntent rows

4 test files, 242 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-encrypted-eff-boundary-currentbase.php
```

Result: exits 0 and emits `encrypted_eff_total_bytes=0`, `encrypted_eff_payload_suppressed=true`, `encrypted_eff_payload_hash_available=false`, `identity_eff_checksum_matches=true`, `identity_eff_strings_redacted=true`, `payload_content_exposed=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-encrypted-eff-boundary-currentbase.php
```

All three reported no syntax errors.

## Non-Overlap

This does not repeat accepted metadata/security encrypted associated-file redaction, default `/EFF` metadata preflight, catalog/page `/AF` extraction, FileAttachment annotation mirrors, related-file summaries, object-stream FileSpec repair, trailer-root selection, generation repair, name-tree `/Limits`, xref-table/xref-stream current-row selection, or payload exclusion from fallback visible text.

The bounded behavior is only standalone attachment summary redaction for trailer `/Encrypt` crypt-filter policy, especially encrypted `/EFF` payload hashes/byte counts and encrypted FileSpec strings.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, selected trailer/xref parsing, attachment name-tree/page/catalog/annotation walkers, crypt-filter metadata already modeled by markerPDF security tests, and WordPress smoke rendering. Full upstream model parity remains out of scope under the current no-GPU directive: no live OCR, PDFium/pdftext execution, Surya/Torch, Texify, table models, Streamlit/FastAPI workers, decryption, or external PDF tools were run.
