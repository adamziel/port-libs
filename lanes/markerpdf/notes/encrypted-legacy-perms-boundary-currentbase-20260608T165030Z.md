# markerPDF encrypted legacy Perms boundary current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T165030Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260608T165030Z`
Base accepted HEAD: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF import
gets parser text before OCR/model conversion, so the native no-GPU PHP lane
must block encrypted visible text until decryption is available and must keep
security-handler authentication material as review metadata.

PDF Standard security handler `/Perms` is an AES-256 permission validation
string introduced for revision 5 and later. A legacy revision 4 Standard
encryption dictionary may still contain a stray `/Perms` key in malformed or
producer-divergent files, but that value is not applicable to revision 4
permission authentication. The native preflight should inventory that operand
as unexpected review metadata, ignore it for permission authentication, and
avoid exposing raw owner/user or `/Perms` bytes.

## Behavior

`PdfMetadataExtractor::standardAuthenticationReview()` now marks Standard
`/Perms` values on legacy revisions as:

- `applicable_for_revision=false`
- `unexpected_for_revision=true`
- `ignored_for_permission_authentication=true`
- `status=unexpected_permission_digest_for_legacy_revision_review`
- `length_valid=null`

`PdfSecurityPreflight` carries those flags through the authentication-material,
permission-trust, operation-row, permission-preflight, and encryption-review
surfaces. The import review reasons include
`legacy_permission_digest_ignored` while preserving the existing policy
`copy_extract_allowed_after_decryption` and the blocked content boundary
`blocked_until_decryption_password_available`.

Encrypted page text, raw owner/user validation material, raw `/Perms` bytes,
decryption, permission enforcement, Python/model execution, and external PDF
tools remain excluded.

## Evidence

Red-first focused command before the source fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyPermsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 10 assertions, 1 failures`; the first test failed
because `review_reasons` did not include `legacy_permission_digest_ignored`.
The preflight reported the stray legacy `/Perms` as
`permission_digest_ciphertext_review` instead of an unexpected legacy digest.

Focused passing command after source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyPermsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 69 assertions, 0 failures`.

Encrypted-permission family regression command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `75 test files, 7247 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-legacy-perms-boundary-currentbase.php
```

Result: exits 0 and emits `text_blocked=true`,
`import_decision=block_encrypted_content_review_security_metadata`,
`permission_policy=copy_extract_allowed_after_decryption`,
`permission_digest_status=unexpected_permission_digest_for_legacy_revision_review`,
`permission_digest_unexpected_for_revision=true`,
`permission_digest_ignored_for_permission_authentication=true`,
`raw_security_material_exposed=false`, and all
decryption/permission-enforcement/model/external-tool flags false.

## Non-Overlap

This does not repeat accepted encrypted fail-closed text extraction,
well-formed Standard permission decoding, malformed `/P` operand boundaries,
duplicate Standard handler parameters, authentication material inventory for
expected revision 5/6 `/Perms`, crypt-filter method/AuthEvent/key-length
checks, public-key recipient envelopes, trailer `/Encrypt` precedence,
encrypted attachment redaction, DSS/signature/DocMDP/FieldMDP review, OCR/model
execution, or stream-filter `/Crypt` behavior. The bounded new behavior is the
revision boundary for a stray `/Perms` value on legacy Standard revision 4.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner,
dictionary value parser, Standard security handler metadata extractor, security
preflight, text extractor, and WordPress smoke path. Full Standard-handler
decryption, password validation, permission enforcement, public-key CMS/PKCS#7
decoding, live `pdftext`, PDFium rendering, OCR/model execution, and external
PDF tools remain intentionally out of scope.

## Follow-Up

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
and converter behavior: fonts, CMaps, stream filters, xref repair, metadata,
annotations, forms, page geometry, image/filter metadata, and supplied-boundary
table/equation handoffs.
