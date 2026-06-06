# markerpdf encrypted permission parameter operand current-base

## Source truth

- Upstream/PDF security-handler behavior treats Standard dictionary parameters as typed operands: `/Filter` is a name and `/V`, `/R`, and `/Length` are integer entries. Permission bits are not reliable when an explicit Standard security-handler parameter is present but malformed.
- Current no-GPU scope: this slice only updates native security preflight metadata and WordPress/import smoke output. It does not decrypt, validate passwords, run OCR/models, or execute external PDF tools.

## Behavior

- `PdfMetadataExtractor` now records Standard security-handler parameter declaration rows for `/Filter`, `/V`, `/R`, and `/Length` with resolved operand shape, per-entry status, malformed entry counts, and fail-closed declaration review.
- Explicit malformed `/Length` operands keep `key_length_explicit=true` but no trusted `key_length_bits`, and the Standard parameter review reports `malformed_standard_security_handler_parameter_entries`.
- `PdfSecurityPreflight` now surfaces malformed Standard parameter names/counts and adds `standard_security_handler_parameter_operands_malformed` to review reasons. Permission bits are ignored and text extraction stays blocked without decryption.

## Evidence

- Red before source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php` => `1 test files, 6 assertions, 2 failures`; malformed `/Length (128)` still produced `copy_or_extract_allowed_but_decryption_required` and no declaration rows.
- Green focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php` => `1 test files, 144 assertions, 0 failures`.
- Green adjacent family: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionV2DefaultLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php` => `5 test files, 663 assertions, 0 failures`.
- Green wider permission family: `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\\.php$' | sort)` => `39 test files, 3195 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-operand-currentbase.php` emits `permission_policy=permissions_malformed_blocked_without_decryption`, `malformed_parameter_names=["Length"]`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, and all execution flags false.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object resolver, dictionary operand parser, Standard permission preflight, and WordPress example smoke harness.

## Non-overlap

Avoids the accepted duplicate-parameter, invalid AES-256 length, V2 default-length, permission-word operand, auth-material, crypt-filter, and version/revision mismatch clusters. This slice is specifically the explicit malformed Standard parameter operand boundary for direct and resolved `/Length` values.
