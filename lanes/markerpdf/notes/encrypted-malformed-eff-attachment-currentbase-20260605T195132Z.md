# markerpdf encrypted malformed EFF attachment preflight current-base

## Source-truth boundary

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through pdftext/PDFium before OCR/layout/model stages. In this no-GPU PHP lane, encrypted content and attachment payloads remain review-only unless native decryption is explicitly activated.
- PDF crypt-filter role selectors `/StmF`, `/StrF`, and `/EFF` are security-sensitive. A malformed `/EFF` operand such as an array must not be silently replaced by an identity fallback when deciding whether embedded-file payload hashes and checksums are safe to expose to WordPress import review.

## Implementation

- `PdfSecurityPreflight` now exposes `crypt_filter_embedded_file_payload_policy`, `crypt_filter_embedded_file_fail_closed`, and `crypt_filter_embedded_file_boundary` alongside the existing document-text crypt-filter policy.
- `PdfMetadataExtractor` now applies `crypt_filter_role_declaration_review` to associated-file payload redaction, so malformed embedded-file role declarations suppress associated-file payload hashes/checksums even when strings remain clear.
- `PdfAttachmentExtractor` now mirrors that fail-closed role policy for lightweight attachment summaries, preserving FileSpec strings when `/StrF` is clear while suppressing embedded payload-derived fields when `/EFF` is malformed.

## Non-overlap

This does not repeat accepted Standard `/P` decoding, unsigned/out-of-range `/P`, duplicate `/P`, missing `/P`, authentication material, `/Perms`, default `/EFF` inheritance, explicit `/CFM /None`, unsupported/missing crypt-filter methods, duplicate `/CF`, duplicate `/StmF`/`/StrF`/`/EFF` document-text role handling, public-key recipient envelopes, encrypted related-file suppression, duplicate trailer `/Encrypt`, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior.

The bounded behavior here is only malformed Standard `/EFF` role declarations before associated-file and lightweight attachment payload review.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedEffAttachmentCurrentBaseTest.php
```

Result: `1 test files / 23 assertions / 1 failures`; the embedded-file policy field was absent before the patch.

Focused after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedEffAttachmentCurrentBaseTest.php
```

Result: `1 test files / 52 assertions / 0 failures`.

Focused regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedEffAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `8 test files / 1350 assertions / 0 failures`.

Encrypted-permission family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `34 test files / 3164 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-malformed-eff-attachment-currentbase.php
```

Emits `embedded_file_boundary=blocked_by_malformed_embedded_file_crypt_filter_role`, `attachment_payload_suppressed=true`, `attachment_payload_hash_available=false`, `associated_file_payload_hash_available=false`, and all decryption/Python/model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, encryption dictionary parser, crypt-filter role declaration review, encrypted attachment redaction paths, `PdfSecurityPreflight`, and WordPress smoke pattern.

Full Standard security-handler decryption, password validation, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU native parser slice.
