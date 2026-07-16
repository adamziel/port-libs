# markerPDF encrypted permission non-integer token parameter current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T072214Z`

Base accepted HEAD: `60dcadf6ae58edd2be080a4d60eeab37e040e3ca`

## Source truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Under the current no-GPU markerPDF scope, this slice maps the native searchable-PDF parser/security boundary before OCR, model, or renderer stages.
- The PDF Standard security handler uses typed entries: `/Filter` is a name and `/V`, `/R`, and `/Length` are integer parameters. A direct non-integer token such as `/Length 128.5` is malformed and permission bits must stay untrusted.

## Change

- `PdfMetadataExtractor` now validates the first token value for Standard security-handler integer parameters when the operand shape is a direct token.
- Direct non-integer token operands for `/V`, `/R`, or `/Length` now get `standard_security_handler_parameter_non_integer_operand_review` instead of being treated as well-formed merely because they are token-shaped.
- Malformed Standard parameter review keeps encrypted text blocked, marks permission bits unreliable, and returns `copy_or_extract_allowed=null` before WordPress import can rely on `/P`.
- Updated the WordPress encrypted-permission parameter smoke to exercise `/Length 128.5`.

## Red-first evidence

Before the source edit, the new focused test failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard explicit key length is a direct non-integer operand
PASS fails closed when Standard explicit key length resolves to a non-integer operand
FAIL fails closed when Standard explicit key length is a direct non-integer token operand
Values are not identical
Expected: ["encrypted_document","encrypted_text_extraction_blocked","standard_security_handler_parameters_malformed","standard_security_handler_parameter_operands_malformed"]
Actual: ["encrypted_document","encrypted_text_extraction_blocked","copy_or_extract_allowed_but_decryption_required"]
1 test files, 147 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard explicit key length is a direct non-integer operand
PASS fails closed when Standard explicit key length resolves to a non-integer operand
PASS fails closed when Standard explicit key length is a direct non-integer token operand
1 test files, 216 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 42 selected test files (root lock skipped)
42 test files, 3992 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-operand-currentbase.php
emits length_operand_shapes=["token"], length_entry_statuses=["standard_security_handler_parameter_non_integer_operand_review"], permission_bits_reliable=false, copy_or_extract_allowed=null, encrypted_text_blocked=true, executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This does not repeat accepted signed/unsigned `/P`, duplicate/malformed/missing `/P`, reserved-bit review, invalid AES-256 key length, top-level malformed `/Length` literal/composite operands, duplicate Standard parameters, `/V` and `/R` compatibility, authentication-material readiness, `/Perms`, escaped auth keys, EncryptMetadata handling, crypt-filter defaults/AuthEvent/key-length/method-generation checks, public-key recipients, encrypted attachments, trailer `/Encrypt`, DSS/signature review, OCR/model execution, or PDF renderer work. The bounded behavior is only direct non-integer token classification for Standard integer parameters before trusting encrypted permission bits.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, encryption dictionary scanner, Standard security-handler parameter review, existing permission preflight, encrypted-text guard, and WordPress smoke renderer.

Full Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
