# Encrypted permissions crypt-filter preflight current base, 2026-06-04

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260604T202539Z`

Base accepted HEAD: `a4e1780beea9042e5770710504694d9e38c8c798`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium before OCR/layout/model conversion. The native PHP lane therefore keeps encrypted document content fail-closed unless a native decryption component is explicitly activated.
- Adobe PDF Reference 1.7 crypt-filter rules define `/StmF` and `/StrF` as the default crypt filters for document streams and strings, `/EFF` as the embedded-file stream filter, and `Identity` as a standard crypt filter that can leave selected streams unencrypted. Crypt filters with encryption methods such as `V2`, `AESV2`, or `AESV3` still require authorization/decryption; missing or unsupported filters must fail closed for import.

## Implemented Behavior

- `PdfSecurityPreflight` now emits `crypt_filter_content_review` at the top level, under `encryption`, and under `permission_preflight`.
- The review records role rows for `document_streams` (`/StmF`), `document_strings` (`/StrF`), and `embedded_file_streams` (`/EFF`), including selected filter name, method, auth event, key length, identity/encrypted/missing status, and safe policy labels.
- Identity filters are recorded as review metadata, but `native_text_extraction_allowed_now` remains false while the document is encrypted. Copy/extract permission bits still do not authorize visible WordPress import without decryption.
- Missing declared crypt filters, such as an `/EFF` name absent from `/CF`, are reported as `missing_declared_crypt_filter` with `missing_declared_filter_fail_closed`.
- Raw owner/user key material, encrypted visible text, decryption, permission enforcement, Python/model work, and external PDF tools remain excluded.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct Standard `/P` permission preflight, unsigned permission-word normalization, malformed reserved-bit review, unsupported handler review, Standard authentication digest review, public-key recipient envelope inventory, indirect encryption operand resolution, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is specifically crypt-filter content-role preflight for encrypted Standard security-handler imports.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php` failed with missing `crypt_filter_content_review` and `crypt_filter_content_review_count`, reaching `1 test files, 8 assertions, 1 failures`.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php` passed with `1 test files, 55 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-permission-preflight-currentbase.php` emitted `text_blocked=true`, `permission_policy=copy_extract_allowed_after_decryption`, `text_content_policy=review_only_encrypted_document_boundary`, `embedded_file_payload_policy=missing_declared_filter_fail_closed`, `identity_role_names=["document_streams"]`, `encrypted_role_names=["document_strings"]`, `missing_role_names=["embedded_file_streams"]`, and all decryption/permission-enforcement/model/external-tool flags false.

## Status Delta

- Focused PHP behavior tests move `1089 -> 1090` PASS cases.
- WordPress scenarios move `1089 -> 1090`.
- Mapped focused markerPDF/PDF semantics add `pdfEncryptedPermissionCryptFilterPreflightCurrentBaseBehaviors=1`; no upstream denominator-total change is claimed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, encryption metadata parser, crypt-filter metadata parser, encrypted text fail-closed gate, Standard permission review, and security preflight report path.

Full Standard security-handler decryption, password validation, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, revocation checking, trust-chain validation, OCR/model execution, and external PDF tooling remain out of scope for this no-GPU native parser slice.
