# Encrypted Standard Auth Material Policy Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T223558Z`
Base: `c5c14bd99fa330d27c77e6af2133453dccf48a5a`

## Source Truth

This stays inside the no-GPU markerPDF scope. The upstream-relevant boundary is native searchable-PDF security preflight before text extraction: Standard security handler authentication material (`/O`, `/U`, `/OE`, `/UE`) and revision-six permission digest material (`/Perms`) must be complete and structurally usable before encrypted permission bits can be trusted. This patch does not attempt password authentication, decryption, permission enforcement, OCR, raster rendering, Python execution, or model execution.

## Behavior

`PdfSecurityPreflight` now exposes non-secret summary fields on both `permission_preflight` and `encryption` review output:

- `standard_authentication_material_policy`
- required-entry counts, present/missing/unresolved/length-mismatch/duplicate entry names
- per-entry status labels
- `/Perms` digest status, length validity, and duplicate-entry markers

The policy is fail-closed for missing, duplicated, unresolved, or malformed authentication material and for missing/malformed/duplicated `/Perms` digest material. Raw authentication or digest bytes are not surfaced in the review summary.

## Evidence

Red-first focused run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthMaterialPolicyCurrentBaseTest.php`

Result: failed with 2 failures / 11 assertions because the new policy summary keys were `NULL` for length-mismatched `/O` and `/UE` material and for missing `/Perms`.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthMaterialPolicyCurrentBaseTest.php`

Result: 1 test file / 51 assertions / 0 failures.

Adjacent encrypted authentication family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthMaterialPolicyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthenticationTrustCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationAuthReadinessCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateAuthMaterialCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDigestOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedAuthKeysCurrentBaseTest.php`

Result: 10 test files / 951 assertions / 0 failures.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-material-policy-currentbase.php`

Result: exits 0 and reports `plain_text_blocked=true`, `standard_authentication_material_policy=standard_authentication_material_length_mismatch`, `standard_authentication_length_mismatch_required_entries=["owner_validation","user_encryption_key"]`, `standard_authentication_permission_digest_status=permission_digest_ciphertext_review`, `standard_authentication_ready_for_password_attempt=false`, and all execution flags false for decryption, permission enforcement, Python/models, and external PDF tools.

## Non-Overlap

This does not repeat the accepted duplicate crypt-filter-name, duplicate auth material, digest operand, escaped auth key, AuthEvent, unsupported crypt filter, or crypt-filter-role slices. It adds a top-level import-review policy summary over existing authentication/digest readiness so WordPress import code can make a conservative decision without inspecting raw encrypted key material.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP parser/security preflight/text extractor test fixtures and a local WordPress smoke. GPU/OCR/model execution, external PDF tools, and live services remain intentionally out of scope.
