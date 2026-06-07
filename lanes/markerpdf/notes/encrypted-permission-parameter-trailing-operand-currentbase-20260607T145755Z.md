# Encrypted Permission Parameter Trailing Operand Current Base

- Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260607T145755Z`
- Base accepted HEAD: `8209e40a422edc00341bc56256bb3ab645e8d2d2`
- Scope: native no-GPU encrypted Standard security-handler permission preflight.

## Source Truth

The PDF security handler dictionary carries top-level Standard parameters such as
`/Filter`, `/V`, `/R`, and `/Length`. Permission bits are not reliable when one
of those parameter values is malformed. This slice extends the existing
top-level operand boundary used by permission/authentication entries to Standard
`/Length` parameter declarations, so values like `/Length 128 9 0 R` and
`/Length 128 256` fail closed instead of trusting the first integer token.

This is native searchable-PDF parser behavior only. It does not decrypt content,
execute permission enforcement, run Python, invoke OCR/model code, rasterize
pages, or call external PDF tools.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when Standard Length is followed by an indirect trailing operand
FAIL fails closed when Standard Length is followed by a name trailing operand
1 test files, 6 assertions, 2 failures
```

The failure showed the previous behavior reported
`copy_or_extract_allowed_but_decryption_required` for a valid-looking
`/Length 128` prefix even when an extra operand followed it.

The slash-name draft in the red-first run was replaced before final verification
because a name object after a dictionary value is key syntax in this parser's
dictionary scanner. The final green fixture set covers the intended top-level
operand shapes with an indirect reference (`9 0 R`) and a numeric token (`256`).

## Patch

- `PdfMetadataExtractor::standardSecurityHandlerParameterDeclarationReview()`
  now uses `dictionaryTopLevelValueReviews()` for Standard handler parameters.
- Entries with `trailing_operand=true` now receive
  `standard_security_handler_parameter_trailing_operand_review` plus operand
  shape/preview/reference metadata.
- The permission preflight treats that malformed parameter review as fail-closed:
  `standard_security_handler_malformed_parameters`,
  `permissions_malformed_blocked_without_decryption`, and
  `permission_bits_reliable=false`.

## Focused Evidence

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-trailing-operand-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-trailing-operand-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php
1 test files, 186 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterSelectedEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
6 test files, 1008 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php' | sort) lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
52 test files, 4647 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-trailing-operand-currentbase.php
exits 0; emits permission_source=standard_security_handler_malformed_parameters, length_entry_statuses=[standard_security_handler_parameter_trailing_operand_review], encrypted_text_blocked=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat the existing malformed single-token `/Length`, duplicate
Standard parameter, malformed `/Filter`, direct `/P` trailing operand, indirect
`/P` trailing operand, authentication trailing operand, trailer `/Encrypt`, or
crypt-filter parameter slices. It only covers trailing top-level operands on
Standard security-handler parameter declarations.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
dictionary top-level value-review helper and existing encrypted permission
preflight fields. GPU/model/OCR work remains intentionally out of scope.

## Root Harness

Not run - isolated micro-slice.
