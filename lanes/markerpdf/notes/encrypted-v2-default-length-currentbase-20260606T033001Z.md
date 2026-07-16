# markerPDF encrypted V2 default Length current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T033001Z`

Base accepted HEAD: `9f34aa3800e8b64840ef7a3a88ee5e5688842b1f`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path delegates text extraction to PDF parser/PDFium-style sources before model stages, so encrypted-document admission is a native parser/security preflight boundary in this no-GPU PHP lane.
- Adobe PDF Reference 1.7 encryption dictionary Table 3.18 states that top-level `/Length` is optional only for encryption `/V` values `2` or `3`, must be 40-128 bits in multiples of 8, and defaults to 40 bits: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf

## Implemented Behavior

- `PdfMetadataExtractor` now records omitted Standard `/V 2` top-level `/Length` as:
  - `key_length_bits=40`
  - `key_length_explicit=false`
  - `key_length_defaulted=true`
  - `key_length_source=standard_security_handler_v2_default_40_bit`
- Standard parameter review now reports `key_length_status=standard_security_handler_key_length_default_40_bit`, `key_length_present=false`, `key_length_valid=true`, and no parameter violations for valid `/V 2 /R 3` dictionaries.
- Permission preflight remains fail-closed for text import: decoded copy/extract bits are review metadata only until password validation and decryption exist.
- Encrypted page text, owner/user authentication bytes, hex key material, permission enforcement, decryption, Python/model execution, and external PDF tools remain unavailable and unexecuted.

## Non-Overlap

This does not repeat accepted Standard permission bit decoding, revision-bit applicability, `/V 1` implicit 40-bit key length, AES-256 `/V 5` missing-Length fail-closed handling, explicit `/Length 40` revision-2 fixtures, duplicate Standard parameters, unsupported version/revision mismatch, malformed `/P` operands, duplicate `/P`, authentication material review, `/Perms`, crypt-filter role/method/AuthEvent/key-length review, trailer `/Encrypt` precedence, encrypted associated-file redaction, signature review, stream-filter `/Crypt`, OCR/model execution, or external PDF tooling.

The bounded behavior is only Standard `/V 2` omitted `/Length` provenance before encrypted permission preflight.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionV2DefaultLengthCurrentBaseTest.php` failed with `1 test files, 17 assertions, 1 failures` because `key_length_bits` was missing.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionV2DefaultLengthCurrentBaseTest.php` passed with `1 test files, 61 assertions, 0 failures`.
- Adjacent encrypted permission/key-length regression: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionV2DefaultLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyImplicitLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `7 test files, 1200 assertions, 0 failures`.
- Broader encrypted security sweep: `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(EncryptedPermission|Security).*CurrentBaseTest\\.php$|PdfSecurityPreflightTest\\.php$' | sort)` passed with `54 test files, 4592 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-v2-default-length-currentbase.php` emitted `encrypted_text_blocked=true`, `key_length_bits=40`, `key_length_defaulted=true`, `key_length_source=standard_security_handler_v2_default_40_bit`, `parameter_key_length_status=standard_security_handler_key_length_default_40_bit`, `permission_policy=copy_extract_allowed_after_decryption`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint: `php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfEncryptedPermissionV2DefaultLengthCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-v2-default-length-currentbase.php` passed.
- Whitespace check: `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused PHP behavior tests move `2361 -> 2363` PASS cases.
- WordPress scenarios move `2023 -> 2024`.
- No upstream benchmark denominator increase is claimed.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, encryption dictionary parser, Standard security-handler parameter review, Standard permission word review, encrypted text fail-closed gate, and security preflight report path.

Full Standard security-handler password validation, permission authentication, stream/string decryption, permission enforcement, public-key CMS/PKCS#7 recipient permission decoding, signature validation, revocation checking, trust-chain validation, OCR/model execution, and external PDF tooling remain out of scope for this no-GPU native parser slice.
