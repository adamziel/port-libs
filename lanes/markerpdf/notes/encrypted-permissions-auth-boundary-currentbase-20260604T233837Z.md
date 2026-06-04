# markerPDF encrypted permissions authentication boundary current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260604T233837Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` opens PDF documents through pdftext/PDFium-style handling before conversion. The native PHP lane keeps encrypted content fail-closed unless a separate native decryption component is activated.
- PDF Standard security-handler permission flags in `/P` are only review metadata unless native decryption/password validation is available. Standard handlers also require authentication material: `/O` and `/U` for revisions 2-4, and `/O`, `/U`, `/OE`, `/UE`, plus `/Perms` for revisions 5-6. This slice inventories whether that material is complete enough for a future password attempt without validating passwords, authenticating permissions, decrypting streams, or enforcing rights.

## Implemented behavior

- `PdfSecurityPreflight` now emits `standard_authentication_material_review` in both `permission_preflight` and top-level `encryption` review metadata.
- The review summarizes required authentication entries, present/missing/unresolved entries, length mismatches, required entry statuses, revision-5/6 `/Perms` digest presence/length/status, and `ready_for_password_attempt`.
- Import policy remains fail-closed and unchanged: copy/extract permission bits can still be reported as allowed after decryption, but encrypted text is not imported and malformed authentication material is explicitly marked review-only.
- Raw `/O`, `/U`, `/OE`, `/UE`, and `/Perms` bytes are not exposed.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed reserved-bit review, unsupported handler review, Standard authentication digest hashing, public-key recipient envelope inventory, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review.

The bounded new behavior is specifically Standard security-handler authentication-material readiness as part of encrypted permission preflight.

## Focused evidence

- Focused new test: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php` passed with `1 test files, 90 assertions, 0 failures`.
- Focused security regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php` passed with `7 test files, 876 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-permissions-auth-boundary-currentbase.php` emitted `malformed_text_blocked=true`, `malformed_auth_status=standard_authentication_material_incomplete_or_malformed_review`, `malformed_ready_for_password_attempt=false`, `malformed_length_mismatch_entries=["owner_validation","user_validation"]`, `missing_perms_text_blocked=true`, `missing_perms_digest_status=required_permission_digest_missing`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Behavior tests move `1113 -> 1116` pass / `0` fail for the three added focused TestRunner cases.
- WordPress scenarios move `1110 -> 1111` for the added smoke coverage.

## Dependency closure

No new support component is needed. This reuses the native PDF object/trailer parser, Standard encryption dictionary parser, authentication-entry digest review, encrypted-text fail-closed gate, and security preflight report path.

Full password validation, Standard security-handler decryption, permission authentication from `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, revocation checks, and trust-chain validation remain out of scope for this no-GPU/no-model markerPDF slice.
