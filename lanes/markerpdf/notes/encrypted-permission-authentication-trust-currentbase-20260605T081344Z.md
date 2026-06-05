# markerPDF encrypted permission authentication trust preflight

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T081344Z`

Base: `eabf2addac7c2c5b012c94b74de9b49f75b6cfef`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU PHP lane, encrypted-document admission is a native PDF parser/security preflight boundary before any OCR/layout/model stage. For the PDF Standard security handler, `/P` can be decoded as review metadata, but revisions 5 and 6 also carry encrypted `/Perms` validation bytes; effective permission trust still requires password validation, permission authentication, and decryption.

## Implementation

`PdfSecurityPreflight` now emits an additive `permission_authentication_trust_review` in both `permission_preflight` and the summarized `encryption` block. It preserves the accepted syntactic `permission_bits_reliable` meaning while adding explicit unauthenticated trust fields:

- `permission_bits_authentication_required`
- `permission_bits_authenticated`
- `authenticated_permission_bits_reliable`
- `permission_authentication_status`

Complete revision-6 Standard material now reports `permission_bits_decoded_but_unauthenticated_ready_for_password_attempt`; missing `/Perms` reports `required_permission_digest_missing_before_permission_authentication`; legacy Standard handlers report `permission_bits_decoded_but_password_not_validated`. The review remains non-executing and never exposes owner/user validation bytes, file-key bytes, `/Perms` bytes, decrypted content, or permission-enforcement results.

## Evidence

Red-first focused run after adding the test and before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthenticationTrustCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
... Undefined array key "permission_authentication_trust_review" ...
1 test files, 17 assertions, 3 failures
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthenticationTrustCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks complete revision six permission bits decoded but unauthenticated before import decisions
PASS marks missing revision six permission digest before trusting decoded permission bits
PASS keeps legacy Standard permission bits review-only until password validation exists
1 test files, 99 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-authentication-trust-currentbase.php
```

Result: exits 0 and emits `complete_permission_bits_reliable=true`, `complete_permission_bits_authenticated=false`, `complete_authenticated_permission_bits_reliable=false`, `complete_authentication_status=permission_bits_decoded_but_unauthenticated_ready_for_password_attempt`, `missing_perms_authentication_status=required_permission_digest_missing_before_permission_authentication`, `missing_perms_digest_status=required_permission_digest_missing`, `raw_auth_material_exposed=false`, and all decryption/permission-enforcement/model/external-tool flags false.

## Non-overlap

This does not repeat accepted Standard `/P` bit decoding, unsigned word normalization, duplicate/malformed/missing permission word handling, revision-gated permission bits, Standard `/V` `/R` `/Length` parameter validation, crypt-filter method/AuthEvent/key-length fail-closed handling, encrypted associated-file metadata redaction, public-key recipient envelope review, or signature/DSS/DocMDP permission review. The bounded behavior is only the high-level trust status that separates decoded permission-bit review from authenticated permission trust.

## Dependency closure

No new support component is needed. This reuses native PDF encryption dictionary parsing, Standard authentication-material review, encrypted text blocking, and the existing security preflight. Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS permission decoding, permission enforcement, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
