# markerPDF encrypted AES-256 length permission preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T073619Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T073619Z`
Accepted base: `edeac0d59e2932eecdef96341078d50d2caa9227`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF import path consumes parser text/metadata before model/OCR stages, so encrypted-document admission is a native parser/security preflight boundary for this no-GPU PHP lane.
- PDF Standard security-handler review uses the `/V`, `/R`, top-level `/Length`, and `/P` entries to decide whether permission bits are coherent review metadata. For `/V 5` AES-256 dictionaries, the top-level key length must resolve to the 256-bit Standard handler boundary before this lane trusts `/P` copy/extract flags.
- This slice remains preflight-only: it does not decrypt PDF bytes, validate passwords, authenticate `/Perms`, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user validation bytes.

## Implementation

- `PdfMetadataExtractor::standardSecurityHandlerKeyLengthReview()` now treats Standard `/V 5` dictionaries with absent or unresolved top-level `/Length` as `missing_standard_security_handler_key_length_review`.
- `PdfMetadataExtractor::standardSecurityHandlerParameterReview()` now emits `missing_standard_security_handler_key_length` before the permission-preflight layer trusts decoded `/P` bits.
- `PdfSecurityPreflight` already gates permission trust on malformed Standard security-handler parameters, so the new metadata violation automatically reports `standard_security_handler_malformed_parameters`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `permission_bits=[]`, and `native_text_extraction_allowed_now=false`.
- Added `PdfEncryptedPermissionAes256LengthCurrentBaseTest.php` with missing and unresolved top-level `/Length` fixtures.
- Added `wordpress-pdf-encrypted-aes256-length-preflight-currentbase.php` to show the WordPress block metadata boundary without leaking encrypted content or raw authentication material.

## Red-First Evidence

Before the implementation change, a current-base probe for `/Filter /Standard /V 5 /R 6` with no top-level `/Length` reported the encrypted document as copy-permitted after decryption:

```text
policy=copy_extract_allowed_after_decryption
source=standard_security_handler_permissions
permission_bits_reliable=true
copy_or_extract_allowed=true
standard_security_handler_parameter_review.key_length_status=standard_security_handler_key_length_default_or_unavailable_review
standard_security_handler_parameter_review.parameters_well_formed=true
```

That meant a malformed AES-256 dictionary could make permission bits look reliable before native decryption support exists.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard AES256 security handler omits top-level key length
PASS fails closed when Standard AES256 security handler length operand is unresolved
1 test files, 144 assertions, 0 failures
```

Adjacent encrypted-permission/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1866 assertions, 0 failures
```

Broader security metadata sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurity*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1117 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-aes256-length-preflight-currentbase.php
```

The smoke exits 0 and emits `encrypted_text_blocked=true`, `permission_source=standard_security_handler_malformed_parameters`, `parameter_violations=["missing_standard_security_handler_key_length"]`, `key_length_status=missing_standard_security_handler_key_length_review`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed `/P` token/composite/unresolved-reference review, duplicate `/P` review, revision-gated permission bits, out-of-range permission words, malformed reserved bits, missing `/P`, missing `/R`, invalid declared key length, Standard authentication digest/material review, public-key recipient permission review, crypt-filter default/content-role/AuthEvent/key-length review, encrypted associated-file redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is only absent or unresolved Standard `/V 5` top-level `/Length` before permission-bit trust.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary scalar resolution, encryption metadata extractor, Standard security-handler parameter review, security preflight report, text-extraction encrypted fail-closed gate, and WordPress smoke renderer. Full PDF decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
