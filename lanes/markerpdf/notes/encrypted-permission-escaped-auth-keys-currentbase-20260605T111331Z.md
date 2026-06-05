# markerPDF encrypted permission escaped auth keys current-base slice

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T111331Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- This no-GPU lane maps the native searchable-PDF/security preflight boundary before markerPDF would hand a document to OCR/layout/model stages.
- PDF dictionary keys are names, and names can use `#xx` hex escapes. The existing parser already uses decoded top-level keys for much of the encryption dictionary; Standard authentication material must use the same boundary for `/O`, `/U`, `/OE`, `/UE`, and `/Perms`.

## Implementation

- `PdfMetadataExtractor` now resolves Standard authentication entries and revision-5/6 `/Perms` validation bytes through the token-aware top-level dictionary entry parser.
- Escaped keys such as `/#4F`, `/#55`, `/#4F#45`, `/#55#45`, `/#50`, and `/#50erms` are decoded before review-only authentication metadata is built.
- The preflight still does not validate passwords, authenticate `/Perms`, decrypt streams or strings, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user/file-key/digest bytes.

## Tests and smoke

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedAuthKeysCurrentBaseTest.php
```

Failed before the parser change with `Expected: true Actual: false` after 11 assertions because escaped `/Perms` was not detected.

Passing focused verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedAuthKeysCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthenticationTrustCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `5 test files, 1009 assertions, 0 failures`.

Expanded encrypted-permission/security preflight verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php
```

Result: `25 test files, 2577 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-escaped-auth-keys-currentbase.php
```

The smoke emits `encrypted_text_blocked=true`, `auth_material_status=standard_authentication_material_ready_for_password_attempt`, `permission_digest_status=permission_digest_ciphertext_review`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted Standard permission bit decoding, unsigned/out-of-range `/P`, duplicate `/P`, indirect valid operands, malformed token operands, top-level AES-256 key-length review, `/Perms` trust-state review, crypt-filter method/AuthEvent/key-length/role review, public-key recipient envelopes, encrypted associated-file redaction, xref `/Encrypt` precedence, stream-filter `/Crypt` DecodeParms, or signature ByteRange/DSS/DocMDP work. The bounded behavior is only escaped-name Standard authentication and permission-digest keys inside the selected encryption dictionary.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner and top-level dictionary parser. Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
