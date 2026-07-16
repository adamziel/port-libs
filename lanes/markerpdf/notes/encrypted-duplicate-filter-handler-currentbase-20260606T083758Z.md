# markerpdf encrypted duplicate Filter handler current-base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T083758Z`
Base: `3d936b88c8478a24a1c25b0972efd5d6a8b2a3d9`

## Source truth

Upstream markerPDF delegates native searchable-PDF parsing to the PDF parser/text extractor path before any OCR/model handoff. Under this no-GPU lane scope, encrypted-document handling stays in native security preflight: do not decrypt, do not validate passwords, do not enforce permissions, and fail closed before WordPress import trusts permission metadata.

PDF encryption dictionaries select the security handler through top-level `/Filter`. Duplicate handler declarations are ambiguous because a parser that selects the first declaration may see `/Standard` while the existing last-value selection can see an unsupported vendor handler. This patch records that ambiguity as review metadata and suppresses permission-bit grants.

## Behavior delta

- Added `security_handler_declaration_review` metadata for duplicate top-level Encrypt dictionary `/Filter` declarations.
- Permission preflight now treats duplicate security-handler `/Filter` entries as malformed/ambiguous handler declarations with boundary `blocked_encrypted_security_handler_malformed`.
- Standard `/P` permission words may still be decoded as review metadata, but `permission_bits_reliable`, `copy_or_extract_allowed`, `allowed`, `denied`, and `permission_bits` are suppressed while the duplicate handler declaration is unresolved.
- Added a WordPress smoke proving encrypted text and authentication material stay out of generated import output.

## Red-first evidence

Current-base probe before the source edit selected the last handler `/VendorSecurity`, had no `security_handler_declaration_review`, and classified the document through the generic unsupported-handler path. The new focused test captures the intended fail-closed duplicate-handler boundary.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateFilterHandlerCurrentBaseTest.php` => `1 test files, 70 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` => `43 test files, 4062 assertions, 0 failures`

Final lint, example smoke, and whitespace verification are recorded in the worker final report.

## Non-overlap

This does not repeat existing encrypted preflight coverage for malformed single `/Filter` operands, duplicate Standard `/P`, duplicate Standard `/Length`, crypt-filter role/method/AuthEvent/key-length gates, duplicate trailer `/Encrypt`, public-key recipients, DSS/signature review, owner/user authentication material validation, or any OCR/model worker behavior.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF dictionary scanner and security preflight path. It does not run GPU/model code, Python, pypdfium, PIL, external PDF tools, or live-service providers.
