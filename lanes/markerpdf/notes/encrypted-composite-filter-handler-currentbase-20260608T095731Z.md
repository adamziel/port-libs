# markerpdf encrypted composite Filter handler current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T095731Z`
Base: `f70b19bb2fdd4ee45a2724fea0c7460448562a62`

## Source truth

Upstream markerPDF delegates searchable-PDF parsing to native parser/PDFium/pdftext paths before any OCR/model handoff. Under the current no-GPU markerPDF scope, encrypted-document handling remains a native security preflight boundary: do not decrypt, do not validate passwords, do not enforce permissions, and do not trust permission grants before import.

PDF encryption dictionaries select the security handler through the top-level `/Filter` name. A composite selector such as `/Filter [/Standard]` is not a valid handler name and must be treated as a malformed handler declaration, not as a usable Standard handler and not as an unsupported-handler permission grant boundary.

## Behavior delta

- `PdfMetadataExtractor::securityHandlerDeclarationReview()` now emits fail-closed `security_handler_declaration_review` metadata for single malformed non-string top-level `/Filter` operands.
- Composite handler operands produce `malformed_security_handler_filter_entries_review`, `malformed_entries=true`, `fail_closed=true`, and entry status `security_handler_filter_composite_operand_review`.
- Permission preflight uses the existing `blocked_encrypted_security_handler_malformed` boundary, clears trusted permission-bit grants, and keeps `copy_or_extract_allowed=null` while still exposing decoded `/P` as review-only metadata.
- Literal-string and hex-string `Standard` operands continue through the accepted Standard parameter malformed path, so this does not change the existing string operand behavior.
- Added a WordPress smoke proving encrypted text and Standard authentication material stay out of generated import output.

## Red-first evidence

Before the source edit, the new focused test failed because `/Filter [/Standard]` was classified through the generic unsupported-handler path without declaration-level malformed handler review:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeFilterHandlerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when selected Encrypt dictionary uses composite security-handler Filter operand
Expected review reasons: encrypted_document, encrypted_text_extraction_blocked, security_handler_declaration_malformed, malformed_security_handler_filter_entries
Actual review reasons: encrypted_document, encrypted_text_extraction_blocked, encryption_handler_permissions_unsupported
1 test files, 3 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeFilterHandlerCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateFilterHandlerCurrentBaseTest.php
3 test files, 309 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
64 test files, 6449 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-composite-filter-handler-currentbase.php
```

Expected smoke flags include `encrypted_text_blocked=true`, `permission_policy=permissions_malformed_blocked_without_decryption`, `content_extraction_boundary=blocked_encrypted_security_handler_malformed`, `security_handler_declaration_status=malformed_security_handler_filter_entries_review`, `declaration_entry_operand_shapes=["array"]`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final lint and whitespace verification are recorded in the worker final report.

## Non-overlap

This does not repeat accepted encrypted preflight coverage for literal-string or hex-string Standard `/Filter` operands, duplicate top-level `/Filter` declarations, public-key `/SubFilter` boundaries, duplicate trailer `/Encrypt`, duplicate Standard `/P`, duplicate Standard parameters, crypt-filter role/method/AuthEvent/key-length/default gates, duplicate authentication material, permission digest readiness, public-key recipients, DSS/signature review, owner/user password validation, OCR/model execution, PDFium rendering, or external PDF tools.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF dictionary scanner, security-handler declaration review, permission preflight, encrypted-text guard, and WordPress smoke renderer. Full Standard password validation, decryption, `/Perms` authentication, permission enforcement, public-key CMS decoding, live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, PDFium rendering, and external PDF tools remain intentionally out of scope.
