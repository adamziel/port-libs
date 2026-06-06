# markerPDF encrypted authentication operand preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T021113Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260606T021113Z`
Base accepted HEAD: `4d33d47fb36955cc34140ee8701a095138710cb8`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path consumes parser/PDF text and metadata before OCR/layout/model work. Under the current no-GPU markerPDF scope, encrypted PDF handling is a native PHP security preflight boundary: classify permission/authentication metadata, block visible text before decryption, and do not validate passwords, authenticate permissions, enforce permissions, execute actions, run Python/model code, or call external PDF tools.

For Standard security-handler revisions 5 and 6, `/O`, `/U`, `/OE`, and `/UE` entries are required authentication/encryption-key ciphertext strings. This slice classifies malformed authentication operands before any future password-attempt readiness decision.

## Behavior

`PdfMetadataExtractor::standardAuthenticationEntryMetadata()` now records `operand_shape`, `selected_entry_operand_shape`, and `entry_operand_shapes` for Standard authentication entries, and distinguishes malformed direct/resolved operands:

- array/dictionary operands become `authentication_entry_composite_operand_review`;
- name/token/other non-string operands become `authentication_entry_non_string_operand_review`;
- unresolved indirect references remain `authentication_entry_unresolved`.

`PdfSecurityPreflight` already consumes the authentication review rows through `standard_authentication_material_review`, so malformed required entries now surface in `required_entry_statuses` and keep `ready_for_password_attempt=false`. The import decision remains fail-closed for encrypted content, and raw `/O`, `/U`, `/OE`, `/UE`, `/Perms`, and page-content bytes stay out of review JSON.

## Red / Green Evidence

Red-first before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL classifies malformed Standard authentication operands before permission trust review
Expected: 'array'
Actual: NULL
1 test files, 8 assertions, 1 failures
```

Focused passing command after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS classifies malformed Standard authentication operands before permission trust review
1 test files, 44 assertions, 0 failures
```

Adjacent encrypted-permission/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 37 selected test files (root lock skipped)
37 test files, 3427 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-operand-currentbase.php
```

Passed with `plain_text_blocked=true`, `owner_validation_status=authentication_entry_composite_operand_review`, `owner_validation_operand_shape=array`, `user_validation_status=authentication_entry_non_string_operand_review`, `user_validation_operand_shape=name`, `ready_for_password_attempt=false`, `permission_authentication_status=permission_bits_decoded_but_authentication_material_incomplete`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Standard permission-bit decoding, unsigned/out-of-range/missing/duplicate/composite `/P` handling, selected duplicate `/P` provenance, reserved-bit review, Standard top-level parameter validation, duplicate authentication material, escaped authentication keys, generation-exact authentication references, `/Perms` missing/duplicate/composite/non-string classification, EncryptMetadata declaration handling, crypt-filter role/default/method/AuthEvent/key-length checks, public-key recipient envelopes, encrypted associated-file redaction, trailer `/Encrypt` precedence, DSS/signature review, OCR/model execution, or stream-filter `/Crypt` DecodeParms behavior. The bounded new behavior is only malformed operand-shape classification for Standard authentication entries `/O`, `/U`, `/OE`, and `/UE`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary parser, object resolver, Standard authentication metadata review, security preflight, encrypted text guard, and WordPress smoke path. Full Standard security-handler decryption, password validation, permission authentication/enforcement, public-key CMS/PKCS#7 permission decoding, signing/signature validation, revocation/trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
