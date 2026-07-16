# markerPDF encrypted permission auth generation current-base slice

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T130132Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T130132Z`
Base accepted HEAD: `4d32467895d9da3885ac59c6f3eee2fa22771330`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through parser-backed PDF boundaries before OCR/model fallback.
- In this no-GPU native PHP lane, encrypted PDFs are security-preflighted without decryption, password validation, permission enforcement, Python, models, or external PDF tools.
- PDF indirect object references include object number and generation. Standard authentication strings (`/O`, `/U`, `/OE`, `/UE`) and revision-5/6 `/Perms` ciphertext must use generation-exact object resolution before deciding whether authentication material is ready for a password attempt.

## Change

- `PdfMetadataExtractor::pdfStringBytesFromValue()` now resolves indirect string operands with the existing generation-aware `objectBodyFromReferenceValue()` helper.
- Added focused coverage for exact generation-1 auth material and stale generation-0 auth material when the current xref-selected objects are generation 1.
- Added a WordPress smoke proving stale-generation auth material keeps encrypted text blocked, leaves `/Perms` unresolved, and does not expose raw current or stale authentication bytes.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthGenerationCurrentBaseTest.php
```

Result:

```text
PASS resolves generation-exact Standard authentication material before encrypted permission review
FAIL fails closed when Standard authentication material references stale object generations
Values are not identical
Expected: false
Actual: true
1 test files, 33 assertions, 1 failures
```

## Verification

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionAuthGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-generation-currentbase.php
```

Result: no syntax errors.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthGenerationCurrentBaseTest.php
```

Result: `1 test files, 86 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurity*CurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncrypted*CurrentBaseTest.php
```

Result: `43 test files, 3900 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-generation-currentbase.php
```

Result: emits `plain_text_blocked=true`, `standard_authentication_ready_for_password_attempt=false`, `permission_digest_status=permission_digest_unresolved`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Standard permission bit decoding, direct auth material length review, duplicate auth material, escaped auth keys, indirect generation-zero operands, trailer `/Encrypt` generation selection, crypt-filter role/default/method/AuthEvent/key-length review, public-key recipient envelopes, encrypted associated-file redaction, stream-filter `/Crypt`, xref `/Encrypt` precedence, signature ByteRange/DSS/DocMDP work, or page-resource/Form XObject inheritance. The bounded behavior is only generation-exact resolution for Standard authentication and `/Perms` string operands inside the selected encryption dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, xref-selected object map, generation-aware object reference helper, Standard security-handler preflight metadata, and WordPress smoke renderer. Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
