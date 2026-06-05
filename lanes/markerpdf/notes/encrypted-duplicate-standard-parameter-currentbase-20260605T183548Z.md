# markerPDF encrypted duplicate Standard parameter current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T183548Z`

Accepted base: `e85b68b3ad66391e6ab52eac56d93e08a3705d7b`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream conversion relies on PDF parser security handling before text conversion. In the native PHP no-GPU lane, encrypted content stays blocked unless a separate decryption/password-validation component is activated.
- PDF encryption dictionaries use security-handler parameter keys such as `/Filter`, `/V`, `/R`, and `/Length`. Duplicate dictionary keys are ambiguous for security preflight, even when the last parsed value is syntactically valid.

## Implementation

- `PdfMetadataExtractor` now records a `standard_security_handler_parameter_declaration_review` when a Standard encryption dictionary declares duplicate security-handler parameters.
- `standardSecurityHandlerParameterReview()` adds `duplicate_standard_security_handler_parameter_entries` to malformed parameter violations when duplicate `/Filter`, `/V`, `/R`, or `/Length` keys are present.
- `PdfSecurityPreflight` exposes duplicate parameter names/counts in the top-level encryption review, permission preflight, and permission-handler review.
- A fixture with `/Length 40 /Length 128` now fails closed even though the selected `/Length 128` value would otherwise be valid and `/P -44` would otherwise decode as copy/extract allowed after decryption.

## Non-overlap

This does not repeat accepted unsigned `/P` normalization, duplicate `/P` conflict handling, out-of-range or composite `/P` operands, missing `/P`, reserved-bit review, Standard authentication-material readiness, duplicate `/O` or `/Perms` auth material, crypt-filter dictionary/role duplicate review, crypt-filter method/AuthEvent/key-length fail-closed handling, duplicate trailer `/Encrypt`, unresolved current trailer `/Encrypt`, public-key recipient/DSS permission review, signature ByteRange/DSS/DocMDP review, encrypted associated-file redaction, OCR/model execution, or object-stream/xref parser work.

The bounded behavior is only duplicate Standard security-handler parameter declarations before encrypted WordPress import permission reliance.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php` failed before the implementation with `1 test files / 3 assertions / 1 failures`; the report still emitted `copy_extract_allowed_but_decryption_required` and no `parameter_declaration_review`.
- Focused after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php` passed with `1 test files / 52 assertions / 0 failures`.
- Adjacent encrypted-permission regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionMissingWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateAuthMaterialCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `8 test files / 1266 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-parameter-currentbase.php` emitted `text_blocked=true`, `policy=permissions_malformed_blocked_without_decryption`, `content_extraction_boundary=blocked_encrypted_permissions_malformed`, `duplicate_parameter_names=["Length"]`, `copy_or_extract_allowed=null`, `permission_bits_reliable=false`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary key scanner, Standard encryption metadata parser, encrypted-text fail-closed gate, and security preflight report path.

Full password validation, Standard security-handler decryption, permission authentication from `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain out of scope for this no-GPU markerPDF slice.
