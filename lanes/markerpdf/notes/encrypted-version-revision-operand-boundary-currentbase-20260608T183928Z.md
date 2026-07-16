# markerpdf encrypted version/revision operand boundary current-base slice

- Session: `port-dev-markerpdf-encrypted-preflight-20260608T183928Z`
- Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T183928Z`
- Base accepted HEAD: `8e0e39ec05700266e1507c3f558fa502f0a0266e`

## Behavior

Added Standard security-handler parameter summaries for malformed `/V` and `/R` operand entries. The row-level declaration review already failed closed for malformed values; this slice now carries the specific malformed entry statuses, operand shapes, and trailing operand shapes through:

- `standard_security_handler_parameter_review`
- `permission_preflight`
- `permission_handler_review`
- `encryption`

Covered current-base encrypted PDFs where `/V 4 9 0 R` and `/R [4]` still decode a `/P` word but cannot safely trust permission bits. Native text remains blocked, `copy_or_extract_allowed` stays `null`, and no decryption, permission enforcement, Python/model code, or external PDF tools execute.

## Evidence

Pre-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 failures, 55 assertions
```

The failures were missing `malformed_parameter_statuses` projections for the new `/V` trailing operand and `/R` composite operand cases.

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 PASS cases, 115 assertions, 0 failures
```

Adjacent focused regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php
5 test files, 651 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-version-revision-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `markerpdf-encrypted-version-revision-operand-boundary-currentbase-smoke` with text blocked, `permissions_malformed_blocked_without_decryption`, malformed parameter names/statuses/shapes for `/V` and `/R`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, and all execution flags false.

## Dependency closure

No new support component is needed. This reuses the existing native PDF dictionary operand scanner, Standard security-handler parameter declaration review, and encrypted preflight projection path. GPU/OCR/model execution remains intentionally out of scope for markerPDF under the current supervisor override.

## Non-overlap

This avoids the accepted and adjacent encrypted-permission work for malformed `/P` operands, duplicate `/Length`, direct/trailing/scalar `/Encrypt` operands, crypt-filter role selection, crypt-filter parameter malformed operands, public-key recipients, and V/R compatibility mismatches. The added behavior is limited to summarizing malformed Standard `/V` and `/R` parameter operand diagnostics in the existing fail-closed permission preflight.
