# Encrypted Auth Trailing Operand Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260607T084627Z`
Accepted base: `bed5eb0577e7b3da6f9d9150fbc09175dc986376`
Date: 2026-06-07 UTC

## Scope

This slice covers native encrypted PDF security preflight for Standard security-handler authentication material. It keeps the no-GPU/no-model boundary: no OCR, Surya/Texify/Torch, decryption, password validation, permission enforcement, PDF action execution, or external PDF tools were run.

The source-truth boundary is that Standard authentication entries (`/O`, `/U`, `/OE`, `/UE`) and revision 5/6 `/Perms` values are single string-like PDF values. A valid first string followed by an additional top-level operand, either directly in the encryption dictionary or after resolving an indirect value object, is malformed preflight input and must not become password-attempt-ready metadata.

## Change

- `PdfMetadataExtractor` now reviews Standard authentication value bodies through the same top-level dictionary value review machinery used by permission words.
- `/O`, `/U`, `/OE`, `/UE`, and `/Perms` values with trailing direct or resolved indirect operands now receive fail-closed review statuses:
  - `authentication_entry_trailing_operand_review`
  - `permission_digest_trailing_operand_review`
- Malformed trailing-operand auth material no longer exposes raw authentication bytes, permission digest bytes, hashes, or password-readiness.
- The WordPress smoke verifies the preflight stays review-only and blocks text import for the malformed encrypted document without invoking model or external-tool paths.

## Evidence

Red-first focused probe before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthTrailingOperandCurrentBaseTest.php
1 test files, 14 assertions, 2 failures
```

Both red failures showed malformed trailing-operand fixtures were still setting `standard_authentication_ready_for_password_attempt=true`.

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthTrailingOperandCurrentBaseTest.php
1 test files, 70 assertions, 0 failures
```

Adjacent encrypted preflight/security suite:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
52 test files, 4955 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-trailing-operand-currentbase.php
```

The smoke exits `0` and reports `owner_auth_status=authentication_entry_trailing_operand_review`, `standard_authentication_ready_for_password_attempt=false`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionAuthTrailingOperandCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-auth-trailing-operand-currentbase.php
git diff --check -- lanes/markerpdf
```

All changed PHP files passed syntax checks, and `git diff --check -- lanes/markerpdf` produced no output.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, encryption dictionary value-review logic, security preflight metadata, and lane-local WordPress smoke harness.

Still intentionally out of scope under the current markerPDF override: full decryption/password validation, public-key CMS recipient validation, signature/DSS/DocMDP enforcement, live OCR/model execution, and PDFium or other external renderer/tool execution.

## Non-overlap

This slice does not repeat prior encrypted permission work for duplicate authentication material, authentication generation mismatches, malformed auth arrays/names, direct or indirect `/P` trailing operands, duplicate `/P`, duplicate security handler/parameter entries, crypt-filter role/method/length/AuthEvent validation, public-key recipients, signature/DSS/DocMDP preflight, or trailer `/Encrypt` selection. It specifically owns trailing operands attached to Standard authentication strings and revision 5/6 `/Perms` strings before password-readiness metadata is emitted.

## Next

Continue with non-overlapping native searchable-PDF parser behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs.
