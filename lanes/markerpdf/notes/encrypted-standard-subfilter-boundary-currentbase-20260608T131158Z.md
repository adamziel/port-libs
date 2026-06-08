# Encrypted Standard SubFilter Boundary Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T131158Z`

## Source Truth

- Upstream markerPDF delegates encrypted-document opening to PDFium/pypdfium2. In this no-GPU/no-live-decryption PHP lane, encrypted content remains blocked and only review-safe security metadata is imported.
- PDF Standard security handlers use `/Filter /Standard` with password authentication and `/P` permission bits. Public-key security handlers use `/SubFilter` to select recipient-envelope semantics. A `/Filter /Standard` dictionary that also declares a public-key `/SubFilter` mixes incompatible permission contracts, so native import must fail closed before trusting `/P`.

## Behavior Added

- `PdfMetadataExtractor` now records a `standard_security_handler_subfilter_incompatible_review` fail-closed declaration when a Standard encryption dictionary declares `/SubFilter`.
- `PdfSecurityPreflight` treats any fail-closed security-handler `/SubFilter` declaration as a permission boundary, including Standard-handler incompatibility, and withholds decoded copy bits from permission review output.
- WordPress smoke coverage keeps encrypted page text and owner/user authentication bytes out of output while surfacing the review-only boundary.

## Red-First Evidence

Before source edits, the focused PHP probe for `/Filter /Standard /SubFilter /adbe.pkcs7.s5 /P -44` reported:

```text
policy=copy_extract_allowed_after_decryption
source=standard_security_handler_permissions
security_handler_subfilter_declaration_status=null
permission_bits_reliable=true
copy_or_extract_allowed=true
```

After the patch, the focused test expects `permissions_malformed_blocked_without_decryption`, `standard_security_handler_subfilter_incompatible_review`, `permission_bits_reliable=false`, and `copy_or_extract_allowed=null`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionStandardSubfilterBoundaryCurrentBaseTest.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php`
- `php lanes/markerpdf/examples/wordpress-pdf-encrypted-standard-subfilter-boundary-currentbase.php`
- `php -l` on changed PHP files
- `git diff --check -- lanes/markerpdf`

## Non-Overlap

This does not repeat accepted public-key duplicate/composite `/SubFilter` recipient-boundary work, Standard `/Filter` operand validation, Standard `/P` malformed/duplicate/indirect operand handling, crypt-filter role/method boundaries, or decryption/password validation. The slice is only the mixed Standard-handler plus public-key `/SubFilter` permission-preflight boundary.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF dictionary parser, security metadata extractor, and preflight reviewer. GPU/model/OCR/PDFium password execution remains intentionally out of scope for this markerPDF lane.
