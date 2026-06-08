# markerPDF encrypted public-key selected-recipient missing current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T172510Z`

Base accepted HEAD: `54a72b0cf0ca3f53a27590245bb3180a2cb6e2d2`

## Source Truth

- Upstream markerPDF keeps encrypted content out of native text import until the security handler can authorize and decrypt it.
- PDF public-key security-handler preflight is metadata-only in this no-GPU lane. For `adbe.pkcs7.s5`, active permission envelopes are selected from crypt-filter `/Recipients`; top-level legacy `/Recipients` are inventoried but are not selected permission envelopes.
- This slice does not parse CMS, match private keys, decrypt streams or strings, enforce permissions, execute Python/models, or expose recipient bytes.

## Implemented

- `PdfMetadataExtractor::publicKeyRecipientReview()` now records `selected_recipient_permissions_missing=true` when public-key recipient arrays exist but none are selected for the active security-handler source.
- `PdfSecurityPreflight` now reports the explicit policy `public_key_selected_recipient_permissions_missing`, boundary `blocked_encrypted_public_key_selected_recipient_permissions_missing`, and review reason `public_key_selected_recipient_permissions_missing` for S5 top-level-only recipient dictionaries.
- Added a WordPress smoke proving encrypted text and public-key recipient bytes remain redacted while the selected-recipient-missing boundary is surfaced.

## Non-Overlap

This does not repeat accepted S5 default crypt-filter recipient selection, legacy S3/S4 top-level recipient precedence, legacy crypt-filter recipient decoy suppression, malformed/duplicate public-key `/SubFilter`, malformed crypt-filter role declarations, public-key recipient trailing operands, Standard permission/authentication review, DSS/signature review, or encrypted associated-file redaction.

The bounded new behavior is only S5 public-key encryption dictionaries with top-level `/Recipients` but no selected crypt-filter recipient envelopes.

## Focused Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySelectedRecipientMissingCurrentBaseTest.php
```

Result before source patch: failed with `1 test files, 3 assertions, 1 failures`; current output reported `public_key_recipient_permissions_undecoded` instead of `public_key_selected_recipient_permissions_missing`.

After source/test/smoke patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySelectedRecipientMissingCurrentBaseTest.php
```

Result: `1 test files, 42 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySelectedRecipientMissingCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyLegacyCryptFilterDecoyCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyCryptFilterRoleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyRecipientOperandBoundaryCurrentBaseTest.php
```

Result: `7 test files, 462 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `76 test files, 7289 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-public-key-selected-recipient-missing-currentbase.php
```

Result: exits `0`; emitted `permission_policy=public_key_selected_recipient_permissions_missing`, `selected_recipient_count=0`, `selected_recipient_permissions_missing=true`, `permission_decode_status=public_key_selected_recipient_envelopes_missing`, `raw_material_exposed=false`, and all CMS/decryption/permission-enforcement/Python/model/external-tool flags false.

## Status Delta

- Focused markerPDF behavior tests move `3334 -> 3335` pass / `0` fail.
- WordPress scenario coverage moves `2716 -> 2717`.
- Mapped upstream inventory is unchanged; this is a current-base native encrypted public-key permission-source boundary over already mapped public-key recipient primitives.

## Dependency Closure

No new support component is needed. This reuses native PDF dictionary parsing, crypt-filter metadata extraction, public-key recipient inventory, encrypted-text fail-closed preflight, and WordPress smoke output.

Full CMS/PKCS#7 parsing, private-key matching, public-key recipient permission decoding, Standard/public-key decryption, password attempts, permission enforcement, OCR/model execution, raster rendering, and external PDF tools remain out of scope. Activating those requires a bounded native cryptographic/decryption component with valid/invalid recipient-envelope, private-key, decrypted text/stream, and permission-bit fixtures.

Root harness status: not run - isolated micro-slice.
