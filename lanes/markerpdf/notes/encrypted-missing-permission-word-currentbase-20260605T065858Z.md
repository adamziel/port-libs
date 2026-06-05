# markerpdf encrypted missing permission word preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T065858Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T065858Z`
Accepted base: `fba6b84d0c8a9b03ee9565a1d3124354c20383da`

## Source Truth

- Upstream `sddai/markerPDF` routes searchable PDF text extraction through parser/security preflight before model and Markdown stages. In this no-GPU native PHP lane, encrypted PDFs stay fail-closed unless a future decryption component is explicitly available.
- PDF Standard security-handler dictionaries require a permission word `/P`. Missing `/P` is not equivalent to a valid but undecoded permission word; WordPress import review must classify it as malformed Standard security metadata before trusting any permission bits.
- This slice remains preflight-only: it does not decrypt PDF bytes, validate passwords, authenticate permission digests, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user validation bytes.

## Implementation

- `PdfMetadataExtractor::standardSecurityHandlerParameterReview()` now records whether a Standard permission word is present and adds `missing_standard_permission_word` when `/P` is absent.
- `PdfSecurityPreflight` now lets malformed Standard-handler parameters take precedence over the generic `permissions_unknown_blocked_without_decryption` branch, including when no Standard permission bits were decoded.
- The handler review payload now reports `malformed_standard_security_handler_parameters_review` for this case while keeping `handler_supported_for_native_permission_review=false`, `permission_bits=[]`, and `copy_or_extract_allowed=null`.
- Added `PdfEncryptedPermissionMissingWordCurrentBaseTest.php` with a Standard encrypted fixture that has `/V`, `/R`, `/Length`, `/O`, and `/U`, but omits `/P`.
- Added `wordpress-pdf-encrypted-missing-permission-word-currentbase.php` to show the WordPress block metadata boundary without leaking encrypted content or raw authentication material.

## Red-First Evidence

Before the implementation change, the new focused regression failed because the fixture was classified as generic unknown permissions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMissingWordCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when Standard encryption dictionary omits required permission word
Expected review reason standard_security_handler_parameters_malformed, actual encryption_permissions_unknown
PASS keeps missing-permission-word encrypted content and key material out of import JSON
1 test files, 10 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMissingWordCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard encryption dictionary omits required permission word
PASS keeps missing-permission-word encrypted content and key material out of import JSON
1 test files, 56 assertions, 0 failures
```

Adjacent encrypted-permission/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMissingWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 13 selected test files (root lock skipped)
13 test files, 1567 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-missing-permission-word-currentbase.php
```

The smoke emits `encrypted_text_blocked=true`, `permission_source=standard_security_handler_malformed_parameters`, `parameter_violations=["missing_standard_permission_word"]`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed `/P` token/composite/unresolved-reference review, duplicate `/P` review, revision-gated permission bits, out-of-range permission words, malformed reserved bits, missing `/R`, invalid top-level key length, Standard authentication digest/material review, public-key recipient permission review, crypt-filter default/content-role/AuthEvent/key-length review, encrypted associated-file redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is only the missing required Standard `/P` permission word boundary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, encryption metadata extractor, Standard security-handler parameter review, security preflight report, text-extraction encrypted fail-closed gate, and WordPress smoke renderer. Full PDF decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
