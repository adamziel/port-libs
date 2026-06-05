# markerPDF encrypted permission version/revision current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T191410Z`

Base accepted HEAD: `985e0f3c2de12ffa6b0f80cf54c8193974e95713`

## Source truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Under the current no-GPU markerPDF scope, this maps the native searchable-PDF parser/security boundary before OCR/layout/model stages.
- Relevant PDF parser behavior: the Standard security handler couples `/V` algorithm generation with `/R` security-handler revision. Supported values in an incompatible pairing are malformed security-handler parameters, so WordPress import must not rely on decoded `/P` permission bits until a coherent password/decryption path exists.

## Change

- `PdfSecurityPreflight` now surfaces Standard security-handler parameter details directly in encryption, permission-preflight, and permission-handler review rows:
  - `standard_security_handler_version_supported`
  - `standard_security_handler_revision_supported`
  - `standard_security_handler_version_revision_compatible`
  - `standard_security_handler_key_length_status`
- When malformed parameters are specifically caused by `/V` and `/R` incompatibility, `review_reasons` now includes `standard_security_handler_version_revision_mismatch` after the existing `standard_security_handler_parameters_malformed` reason.
- Added a WordPress smoke proving encrypted text and Standard authentication material remain redacted and no decryption, permission enforcement, Python/model, or external PDF tooling runs.

## Red-first evidence

Before the source edit, the new focused test failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when Standard security handler version and revision are incompatible
Expected review_reasons to include standard_security_handler_version_revision_mismatch.
PASS keeps version-revision mismatch encrypted payloads out of visible WordPress text
1 test files, 9 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard security handler version and revision are incompatible
PASS keeps version-revision mismatch encrypted payloads out of visible WordPress text
1 test files, 82 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionMissingWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionReservedBitsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
8 test files, 1265 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
33 test files, 3112 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-version-revision-currentbase.php
emits encrypted_text_blocked=true, permission_policy=permissions_malformed_blocked_without_decryption, content_extraction_boundary=blocked_encrypted_permissions_malformed, version_revision_compatible=false, parameter_violations=["standard_security_handler_version_revision_mismatch"], permission_bits_reliable=false, copy_or_extract_allowed=null, raw_key_material_exposed=false, executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This does not repeat accepted encrypted fail-closed text extraction, signed/unsigned/plus `/P` normalization, duplicate/malformed/missing `/P`, reserved-bit review, top-level AES-256 missing/invalid key length, duplicate Standard parameters, Standard authentication-material readiness, duplicate authentication material, `/Perms`, escaped auth keys, EncryptMetadata declaration handling, crypt-filter defaults/AuthEvent/key-length/method-generation checks, public-key recipient envelopes, encrypted associated-file redaction, trailer `/Encrypt` precedence, DSS/signature/DocMDP review, OCR/model execution, or xref/parser work. The bounded behavior is only explicit preflight evidence for Standard `/V` and `/R` incompatibility before trusting permission bits.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, top-level encryption dictionary scanner, Standard security-handler parameter review, existing permission preflight, encrypted-text guard, and WordPress smoke renderer.

Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
