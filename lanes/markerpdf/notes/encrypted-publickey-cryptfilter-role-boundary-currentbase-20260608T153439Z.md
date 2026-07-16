# Encrypted Public-Key Crypt-Filter Role Boundary Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T153439Z`  
Base accepted HEAD: `115e94fb0f21b20d880b87458c0d29bcb825d9db`

## Source Truth

- Upstream `sddai/markerPDF` routes searchable-PDF text through parser-backed extraction before OCR/model fallback. Under this lane's no-GPU scope, encrypted PDFs stay blocked unless native security preflight can safely prove a review-only boundary without attempting decryption, private-key use, CMS parsing, OCR, or external PDF tools.
- PDF public-key `adbe.pkcs7.s5` permission envelopes are selected through crypt filters named by `/StmF`, `/StrF`, and `/EFF`. Those role values must be single name objects. A trailing operand such as `/StmF /DefaultCryptFilter 9 0 R` is malformed and must not silently select `/DefaultCryptFilter` recipients for permission review.

## Behavior Added

- `PdfMetadataExtractor::publicKeyRecipientCryptFilterSelection()` now consumes the strict `crypt_filter_role_declaration_review` rows before selecting public-key crypt-filter recipient envelopes.
- When a selected role declaration is fail-closed, recipient envelopes for that role are inventoried only as suppressed review metadata. They do not contribute selected recipient counts, hashes, or permission sources.
- `PdfSecurityPreflight` now surfaces the malformed public-key role boundary with:
  - `source=public_key_crypt_filter_role_declaration_malformed`
  - `policy=permissions_malformed_blocked_without_decryption`
  - `content_extraction_boundary=blocked_encrypted_public_key_crypt_filter_role_malformed`
  - `selected_public_key_recipient_count=0`
- The WordPress smoke keeps encrypted text blocked, leaves the public-key recipient envelope unselected, redacts raw recipient bytes, and records all no-execution flags as false.

## Red-First Evidence

Before the source changes, the new fixture failed closed only as an undecoded public-key recipient case and still selected the malformed `/StmF` crypt-filter recipient:

```text
Expected review_reasons [encrypted_document, encrypted_text_extraction_blocked, public_key_crypt_filter_role_declaration_malformed, malformed_public_key_crypt_filter_roles, crypt_filter_text_fail_closed]
Actual [encrypted_document, encrypted_text_extraction_blocked, public_key_recipient_permissions_undecoded]
1 test files, 3 assertions, 1 failures
```

## Verification

- Syntax:
  `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`,
  `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`,
  `php -l lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyCryptFilterRoleBoundaryCurrentBaseTest.php`, and
  `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-publickey-cryptfilter-role-boundary-currentbase.php`  
  Result: no syntax errors.
- `php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'`  
  Result: `lane-status json ok`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyCryptFilterRoleBoundaryCurrentBaseTest.php`  
  Result: `1 test files, 65 assertions, 0 failures`.
- Adjacent public-key/crypt-filter regression subset:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyRecipientOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterRoleTrailingOperandCurrentBaseTest.php`  
  Result: `5 test files, 366 assertions, 0 failures`.
- Broad encrypted-permission/security family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php`  
  Result: `74 test files, 7124 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-encrypted-publickey-cryptfilter-role-boundary-currentbase.php`  
  Result: exits 0; emitted `source=public_key_crypt_filter_role_declaration_malformed`, `selected_recipient_count=0`, `unselected_recipient_filters=["DefaultCryptFilter"]`, `role_fail_closed=true`, `raw_material_exposed=false`, and all no-execution flags false.
- `git diff --check -- lanes/markerpdf`  
  Result: passes with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Standard `/P` permission-word parsing, duplicate/malformed `/P`, Standard parameter or authentication operand review, public-key legacy recipient selection, public-key default crypt-filter recipient selection, public-key `/Recipients` operand boundaries, public-key `/SubFilter` declaration boundaries, Standard crypt-filter role trailing operands, unsupported crypt-filter methods, duplicate crypt-filter parameters, encrypted associated-file redaction, DSS/signature review, or decryption/password validation. The bounded behavior is only public-key recipient selection suppression when the selected crypt-filter role declaration itself is malformed.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary scanner, object resolver, strict crypt-filter role declaration review, public-key recipient inventory, security preflight reporting, text extraction encrypted gate, and WordPress smoke renderer. CMS/PKCS#7 parsing, private-key operations, decryption, OCR/model execution, PDFium rendering, and external PDF tooling remain intentionally out of scope.
