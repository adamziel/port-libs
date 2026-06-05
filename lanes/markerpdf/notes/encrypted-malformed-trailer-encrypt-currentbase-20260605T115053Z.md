# markerpdf encrypted malformed trailer Encrypt preflight current-base 2026-06-05

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T115053Z`
- Accepted base: `2ac63d7c81fe9d3c90dfd8cfda46dcfd06c4487c`
- Scope: native no-GPU PDF security preflight only.

## Source-Truth Boundary

PDF trailer `/Encrypt` selection is security-sensitive. When the latest active trailer chain declares a non-null `/Encrypt` operand, import must fail closed if that operand cannot be resolved to a dictionary. The native preflight must not treat the document as unencrypted and must not inherit stale `/Prev` permission bits as if they still governed current content.

This maps the markerPDF no-GPU WordPress import boundary: encrypted PDFs remain review-only until a password/decryption path exists, and malformed current security metadata suppresses stale permission grants.

## Implementation

- `PdfMetadataExtractor` now converts unresolved or malformed non-null selected `/Encrypt` operands into sanitized encrypted metadata:
  - `malformed_encrypt_dictionary=true`
  - `encrypt_dictionary_resolved=false`
  - `encrypt_operand_shape`
  - `encrypt_operand_status`
- Existing `PdfSecurityPreflight` permission logic then classifies the document as:
  - `encrypted=true`
  - `permissions_unknown_blocked_without_decryption`
  - `blocked_encrypted_permissions_unknown`
- No raw key material, encrypted content text, or stale permission grants are exposed.

## Evidence

Red probe before the patch:

- A latest trailer with `/Encrypt 99 0 R` and `/Prev` pointing to a stale valid Standard dictionary reported `PdfSecurityPreflight::encrypted=false` and `permission_preflight.source=unencrypted_document`.

Focused verification after the patch:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedTrailerEncryptCurrentBaseTest.php`
- Result: `1 test files, 51 assertions, 0 failures`

Adjacent family verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
- Result: `26 test files, 2586 assertions, 0 failures`

Smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-encrypted-malformed-trailer-encrypt-currentbase.php`
- Result: emits `plain_text_blocked=true`, `encrypted=true`, `encrypt_operand_status=encrypt_dictionary_unresolved_reference`, `permission_policy=permissions_unknown_blocked_without_decryption`, `stale_permission_grant_suppressed=true`, and no Python/model/external PDF tool execution.

Lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionMalformedTrailerEncryptCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-malformed-trailer-encrypt-currentbase.php`
- Result: no syntax errors.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object/trailer parser, metadata extractor, security preflight, and text extraction fail-closed encryption boundary. No OCR, GPU models, Python workers, external PDF tools, signature validation, permission enforcement, or decryption were executed.

## Non-Overlap

This slice does not repeat accepted Standard permission bit decoding, indirect permission operands, duplicate `/P` handling, crypt-filter roles, `/Encrypt null` precedence, `/Prev` encryption inheritance, generation-specific `/Encrypt` selection, or signature/action review. It covers only malformed current non-null trailer `/Encrypt` operands before stale `/Prev` permission fallback.

## Next

Continue with non-overlapping native searchable-PDF security/parser work: malformed current xref-stream Encrypt operand shapes, encrypted metadata source priority, signature byte-range boundaries, attachment crypt-filter redaction, or other no-GPU parser review paths.
