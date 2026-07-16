# markerPDF encrypted permission digest operand current-base slice

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T005528Z`

Base accepted HEAD: `ff7d31e1397095949e33524eafeb5b7160ae8790`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its conversion path consumes PDF text and metadata before any OCR/layout/model stage, so encrypted-document admission is a native parser/dependency boundary for this no-GPU PHP lane.

For the PDF Standard security handler revisions 5 and 6, `/Perms` is a 16-byte encrypted permissions validation string. The native preflight can inspect whether that ciphertext operand is present, scalar, string-like, and length-compatible, but it must not decrypt it, authenticate it, enforce permissions, or expose raw bytes.

## Change

`PdfMetadataExtractor` now records review-only operand metadata for Standard `/Perms` entries:

- `operand_shape` on each `/Perms` entry;
- `selected_entry_operand_shape` and `entry_operand_shapes` on the selected digest review;
- distinct fail-closed statuses for composite operands (`permission_digest_composite_operand_review`) and non-string operands (`permission_digest_non_string_operand_review`).

`PdfSecurityPreflight` now carries those selected operand fields into `standard_authentication_material_review` and `permission_authentication_trust_review`, so WordPress import callers can see why password-attempt readiness is blocked without parsing low-level entry arrays.

The preflight still does not validate passwords, decrypt Standard security handler data, authenticate `/Perms`, enforce permissions, execute PDF actions, run Python/model code, or expose raw `/O`, `/U`, `/OE`, `/UE`, `/Perms`, or page-content bytes.

## Red / Green Evidence

Red-first command before source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDigestOperandCurrentBaseTest.php
```

Result: `1 test files, 32 assertions, 2 failures`; composite and name `/Perms` operands both collapsed to `permission_digest_entry_unresolved`.

Focused passing command after source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDigestOperandCurrentBaseTest.php
```

Result: `1 test files, 108 assertions, 0 failures`.

Adjacent encrypted-permission regression command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `36 test files, 3383 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-perms-operand-currentbase.php
```

Result: emitted `permission_digest_status=permission_digest_composite_operand_review`, `permission_digest_selected_entry_operand_shape=array`, `ready_for_password_attempt=false`, `permission_authentication_status=permission_digest_malformed_before_permission_authentication`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Standard permission bit decoding, unsigned/out-of-range `/P`, duplicate/malformed/missing `/P`, reserved-bit review, Standard parameter validation, authentication material length readiness, duplicate authentication material, missing `/Perms`, indirect/generation-specific auth material, escaped auth keys, EncryptMetadata declaration handling, crypt-filter role/default/method/AuthEvent/key-length checks, public-key recipient envelopes, encrypted associated-file redaction, trailer `/Encrypt` precedence, DSS/signature/DocMDP review, OCR/model execution, or stream-filter `/Crypt` DecodeParms behavior. The bounded new behavior is only malformed non-string/composite `/Perms` operand classification inside the selected Standard encryption dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level dictionary parser, generation-aware object resolution, encrypted text blocking, and existing security preflight. Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
