# markerpdf encrypted duplicate auth material current-base 2026-06-05

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T122712Z`
- Accepted base: `83d14850b25025929d0658c79f2dae5d9193bbe0`
- Scope: native no-GPU PDF security preflight only.

## Source-Truth Boundary

PDF encryption dictionaries are security-sensitive dictionaries. Duplicate Standard authentication keys (`/O`, `/U`, `/OE`, `/UE`) and duplicate revision 5/6 `/Perms` permission-digest entries are ambiguous password/authentication material. The native preflight may record sanitized length/hash review metadata, but it must not treat duplicate material as ready for password validation or permission trust.

This maps markerPDF's no-GPU WordPress import boundary: encrypted searchable-PDF content stays blocked until a future decryption/password path exists, and duplicate auth material cannot promote decoded permission bits into trusted import decisions.

## Implementation

- `PdfMetadataExtractor` now keeps duplicate declaration review fields for Standard authentication entries:
  - `declared_entry_count`
  - `duplicate_entries`
  - `selected_entry_index`
  - `selected_entry_status`
  - `entry_statuses`
  - `entry_reviews`
- `PdfMetadataExtractor` now preserves the same sanitized review shape for `/Perms` entries.
- `PdfSecurityPreflight` now marks duplicate required auth entries and duplicate permission digests as not ready for password attempts.
- Raw owner/user/file-key/permission-digest bytes remain excluded; no decryption, password validation, permission enforcement, Python/model code, or external PDF tools are executed.

## Evidence

Red probe before the source patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateAuthMaterialCurrentBaseTest.php
```

Result: `1 test files, 11 assertions, 2 failures`; both duplicate `/O` and duplicate `/Perms` fixtures incorrectly reported `standard_authentication_ready_for_password_attempt=true`.

Focused verification after the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateAuthMaterialCurrentBaseTest.php
```

Result: `1 test files, 71 assertions, 0 failures`.

Adjacent encrypted-permission/security verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Result: `29 test files, 2755 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-auth-material-currentbase.php
```

Result: emits `owner_auth_status=authentication_entry_duplicate_entries_review`, `permission_digest_status=permission_digest_duplicate_entries_review`, `standard_authentication_ready_for_password_attempt=false`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level dictionary parser, encryption metadata extractor, security preflight, and WordPress smoke renderer. Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.

## Non-Overlap

This does not repeat accepted Standard permission bit decoding, unsigned/out-of-range `/P`, duplicate `/P`, indirect valid operands, malformed `/P` operands, top-level AES-256 key-length review, missing `/Perms`, escaped auth keys, `/Perms` trust-state review for single entries, crypt-filter method/AuthEvent/key-length/role review, public-key recipient envelopes, encrypted associated-file redaction, malformed current trailer `/Encrypt`, xref `/Encrypt` precedence, stream-filter `/Crypt` DecodeParms, or signature ByteRange/DSS/DocMDP work. The bounded behavior is only duplicate Standard authentication and permission-digest entries inside the selected encryption dictionary.

## Next

Continue with non-overlapping native searchable-PDF parser/security work around xref repair, stream filters, fonts/CMaps, annotations/forms, metadata, page geometry, and image/filter metadata under the no-GPU markerPDF scope.
